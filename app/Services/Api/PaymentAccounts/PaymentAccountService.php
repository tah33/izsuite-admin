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
 */
class PaymentAccountService
{
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
        $account = $this->paymentAccountRepository->create($workspace, $this->withOpeningBalance($data));

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/payment-accounts" entry for the same request.
        ActivityLogService::record('created', "Added payment account {$this->quoted($account)}", $account, ['source' => 'api']);

        return $this->withBalance($account, null, false);
    }

    public function update(Workspace $workspace, int $id, array $data): PaymentAccount
    {
        $account = $this->findOwned($workspace, $id);
        $data    = $this->withOpeningBalance($data);

        if ($this->accountTransactionRepository->existsFor($account)) {
            $this->keepWhatMoneyMovedOn($account, $data);
        }

        $this->paymentAccountRepository->update($account, $data);

        ActivityLogService::record('updated', "Updated payment account {$this->quoted($account)}", $account, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($account->getChanges(), ['updated_at' => true])),
        ]);

        return $this->find($workspace, $account->id);
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
