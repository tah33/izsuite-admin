<?php

namespace App\Services\Api\PaymentAccounts;

use App\Models\User\PaymentAccount;
use App\Models\User\Workspace;
use App\Repositories\User\AccountTransactionRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A business's payment accounts (Doc 05): adding, changing and removing them,
 * each with its recorded balance - its opening balance plus the completed
 * money in, less the completed money out (section 10).
 *
 * Once money has moved through an account its currency stays (section 14)
 * and so do its opening balance and that balance's date (section 16: no
 * direct balance editing after transactions exist). An account with an
 * opening balance or any history is deactivated, never deleted (section 17).
 *
 * Defaults (section 8): an active account allowed a kind of payment can be
 * the default for it - receiving customer money, or paying suppliers and
 * expenses - one of each per currency and location. A default that moves to
 * another currency or location, or stops allowing its kind, stops being the
 * default. One that is deactivated hands its default to the account named
 * to take over, where another could; with none, the default just goes.
 */
class PaymentAccountService
{
    /** What a request names to take over its defaults - never stored on the account itself. */
    private const REPLACEMENTS = ['replacement_receiving_id', 'replacement_payment_id'];

    public function __construct(
        protected PaymentAccountRepository $paymentAccountRepository,
        protected AccountTransactionRepository $accountTransactionRepository,
    ) {}

    /** Every account, each with its recorded balance and whether money has moved through it. */
    public function list(Workspace $workspace): Collection
    {
        $net  = $this->accountTransactionRepository->netByAccount($workspace);
        $used = array_flip($this->accountTransactionRepository->accountsWithHistory($workspace));

        return $this->paymentAccountRepository->getFor($workspace)
            ->each(fn (PaymentAccount $account) => $this->withBalance($account, $net[$account->id] ?? null, isset($used[$account->id])));
    }

    /** One account, with its recorded balance. Another business's answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): PaymentAccount
    {
        $account = $this->findOwned($workspace, $id);

        return $this->withBalance($account, $this->accountTransactionRepository->netFor($account), $this->accountTransactionRepository->existsFor($account));
    }

    public function create(Workspace $workspace, array $data): PaymentAccount
    {
        $account = $this->paymentAccountRepository->create($workspace, $this->withOpeningBalance(Arr::except($data, self::REPLACEMENTS)));

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/payment-accounts" entry for the same request.
        ActivityLogService::record('created', "Added payment account {$this->quoted($account)}", $account, ['source' => 'api']);

        return $this->withBalance($account, null, false);
    }

    /** $data is validated; deactivating a default names the accounts that take over (see deactivate()). */
    public function update(Workspace $workspace, int $id, array $data): PaymentAccount
    {
        $account     = $this->findOwned($workspace, $id);
        $replacement = Arr::only($data, self::REPLACEMENTS);
        $data        = $this->withOpeningBalance(Arr::except($data, self::REPLACEMENTS));

        if ($this->accountTransactionRepository->existsFor($account)) {
            $this->keepWhatMoneyMovedOn($account, $data);
        }

        DB::transaction(function () use ($workspace, $account, $data, $replacement) {
            if ($account->is_active && $this->turnsOff($data, 'is_active')) {
                $this->handOverDefaults($workspace, $account, $replacement);
            }

            $this->paymentAccountRepository->update($account, [...$data, ...$this->defaultsKept($account, $data)]);
        });

        ActivityLogService::record('updated', "Updated payment account {$this->quoted($account)}", $account, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($account->getChanges(), ['updated_at' => true])),
        ]);

        return $this->find($workspace, $account->id);
    }

    /**
     * Makes it the default for $for - receiving or payment - in its currency
     * and location, in place of any other. An inactive account, or one not
     * allowed that kind of payment, cannot be.
     */
    public function makeDefault(Workspace $workspace, int $id, string $for): PaymentAccount
    {
        $account = $this->findOwned($workspace, $id);
        $allowed = PaymentAccount::DEFAULTS[$for];

        if (! $account->is_active || ! $account->{$allowed}) {
            throw ValidationException::withMessages(['for' => ! $account->is_active
                ? 'An inactive account cannot be a default. Activate it first.'
                : ($for === 'receiving' ? 'This account does not take customer payments.' : 'This account does not pay suppliers or expenses.')]);
        }

        DB::transaction(function () use ($workspace, $account, $for) {
            $this->paymentAccountRepository->clearDefault($workspace, $for, $account->currency, $account->location_id);
            $this->paymentAccountRepository->update($account, ["default_{$for}" => true]);
        });

        ActivityLogService::record('updated', "Made {$this->quoted($account)} the default {$for} account for {$account->currency}", $account, [
            'source' => 'api',
            'fields' => ["default_{$for}"],
        ]);

        return $this->find($workspace, $account->id);
    }

    /** Deactivates it - handing its defaults to the accounts named in $replacement, where another could take them. */
    public function deactivate(Workspace $workspace, int $id, array $replacement): PaymentAccount
    {
        return $this->setActive($workspace, $id, false, $replacement);
    }

    public function activate(Workspace $workspace, int $id): PaymentAccount
    {
        return $this->setActive($workspace, $id, true, []);
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $account = $this->findOwned($workspace, $id);

        if (! Decimal::isZero((string) $account->opening_balance) || $this->accountTransactionRepository->existsFor($account)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This account has an opening balance or money recorded through it, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        $this->paymentAccountRepository->delete($account);

        ActivityLogService::record('deleted', "Deleted payment account {$this->quoted($account)}", $account, ['source' => 'api']);
    }

    private function setActive(Workspace $workspace, int $id, bool $active, array $replacement): PaymentAccount
    {
        $account = $this->findOwned($workspace, $id);

        DB::transaction(function () use ($workspace, $account, $active, $replacement) {
            if (! $active && $account->is_active) {
                $this->handOverDefaults($workspace, $account, $replacement);
            }

            $this->paymentAccountRepository->update($account, $active
                ? ['is_active' => true]
                : ['is_active' => false, 'default_receiving' => false, 'default_payment' => false]);
        });

        ActivityLogService::record('updated', ($active ? 'Activated' : 'Deactivated')." payment account {$this->quoted($account)}", $account, [
            'source' => 'api',
            'fields' => $account->wasChanged('is_active') ? ['is_active'] : [],
        ]);

        return $this->find($workspace, $account->id);
    }

    /**
     * A default account going inactive hands each default it holds to the
     * account named for it in $replacement - one that could take it: active,
     * of the same currency and location, allowed that kind of payment. Where
     * no account could, the default simply goes.
     */
    private function handOverDefaults(Workspace $workspace, PaymentAccount $account, array $replacement): void
    {
        $errors = [];

        foreach (array_keys(PaymentAccount::DEFAULTS) as $for) {
            if (! $account->{"default_{$for}"}) {
                continue;
            }

            $candidates = $this->paymentAccountRepository->defaultCandidates($workspace, $account, $for);
            $chosen     = $candidates->firstWhere('id', (int) ($replacement["replacement_{$for}_id"] ?? 0));

            if ($candidates->isNotEmpty() && ! $chosen) {
                $errors["replacement_{$for}_id"] = "Choose the account that becomes the default {$for} account for {$account->currency} instead.";

                continue;
            }

            if ($chosen) {
                $this->paymentAccountRepository->update($chosen, ["default_{$for}" => true]);
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The defaults it keeps through a change: none once it moves to another
     * currency or location - it would clash with that one's own - and not one
     * for a kind of payment it is no longer allowed, or once it is inactive.
     */
    private function defaultsKept(PaymentAccount $account, array $data): array
    {
        $moved = ($data['currency'] ?? $account->currency) !== $account->currency
            || (array_key_exists('location_id', $data) && (int) $data['location_id'] !== (int) $account->location_id);
        $kept  = [];

        foreach (PaymentAccount::DEFAULTS as $for => $allowed) {
            if ($account->{"default_{$for}"} && ($moved || $this->turnsOff($data, 'is_active') || $this->turnsOff($data, $allowed))) {
                $kept["default_{$for}"] = false;
            }
        }

        return $kept;
    }

    /** Whether $data switches $flag off - sent as false, 0 or "0". */
    private function turnsOff(array $data, string $flag): bool
    {
        return array_key_exists($flag, $data) && ! filter_var($data[$flag], FILTER_VALIDATE_BOOLEAN);
    }

    /** Its currency, opening balance and that balance's date, as they are: money has moved on them. */
    private function keepWhatMoneyMovedOn(PaymentAccount $account, array $data): void
    {
        $errors = [];

        if (($data['currency'] ?? $account->currency) !== $account->currency) {
            $errors['currency'] = "Money has moved through this account, so its currency stays {$account->currency}.";
        }

        if (array_key_exists('opening_balance', $data) && bccomp((string) $data['opening_balance'], (string) $account->opening_balance, 2) !== 0) {
            $errors['opening_balance'] = 'Money has moved through this account, so its opening balance stays as it is.';
        }

        if (array_key_exists('opening_balance_date', $data) && $data['opening_balance_date'] !== $account->opening_balance_date) {
            $errors['opening_balance_date'] = 'Money has moved through this account, so its opening balance date stays as it is.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** The recorded balance: the opening balance plus $net, the history's completed money in less out. */
    private function withBalance(PaymentAccount $account, mixed $net, bool $used): PaymentAccount
    {
        $account->setAttribute('balance', Decimal::round(bcadd((string) $account->opening_balance, Decimal::of($net), 10)));
        $account->setAttribute('has_transactions', $used);

        return $account;
    }

    /** An opening balance left empty is zero, not "unknown" - and has no date. Left out, it is left alone. */
    private function withOpeningBalance(array $data): array
    {
        if (array_key_exists('opening_balance', $data) && $data['opening_balance'] === null) {
            $data['opening_balance'] = 0;
        }

        if (array_key_exists('opening_balance', $data) && Decimal::isZero((string) $data['opening_balance'])) {
            $data['opening_balance_date'] = null;
        }

        return $data;
    }

    /**
     * An account of another business - the owner's own other one, or someone
     * else's - answers exactly like one that does not exist.
     */
    private function findOwned(Workspace $workspace, int $id): PaymentAccount
    {
        return $this->paymentAccountRepository->findFor($workspace, $id)
            ?? abort(404, 'Payment account not found.');
    }

    /** The name, in quotes and cut down, so the log entry fits its 255-character column. */
    private function quoted(PaymentAccount $account): string
    {
        return '"'.Str::limit($account->name, 100).'"';
    }
}
