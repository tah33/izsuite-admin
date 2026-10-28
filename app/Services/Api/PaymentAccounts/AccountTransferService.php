<?php

namespace App\Services\Api\PaymentAccounts;

use App\Models\User\AccountTransfer;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\AccountTransferRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transfers between a business's payment accounts (Doc 05 section 13): cash
 * banked, a wallet emptied. A draft can be changed, completed or deleted; a
 * completed transfer moves the money - out of one account, into the other,
 * as two linked rows of their histories - and is voided, never changed, when
 * it was a mistake. A transfer is neither a sale nor an expense.
 *
 * Between accounts in different currencies, what arrived is entered too, and
 * the rate between the two is kept with a value in the base currency
 * (section 14). Fees are recorded as expenses, not here (section 15).
 */
class AccountTransferService
{
    use RefusesConflicts;

    public function __construct(
        protected AccountTransferRepository $accountTransferRepository,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected AccountLedger $accountLedger,
        protected BusinessCalendar $businessCalendar,
    ) {}

    /** Another business's transfer answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): AccountTransfer
    {
        return $this->accountTransferRepository->findFor($workspace, $id) ?? abort(404, 'Transfer not found.');
    }

    /** $data is validated (see SaveTransferRequest); `complete` moves the money at once. */
    public function create(Workspace $workspace, User $user, array $data): AccountTransfer
    {
        $complete = (bool) ($data['complete'] ?? false);
        $columns  = $this->columns($workspace, $data, $complete);

        $transfer = DB::transaction(function () use ($workspace, $user, $columns, $complete) {
            $transfer = $this->accountTransferRepository->create($workspace, [
                ...$columns,
                'status'     => $complete ? AccountTransfer::COMPLETED : AccountTransfer::DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->accountLedger->recordTransfer($workspace, $transfer);

            return $transfer;
        });

        // Logged here, by what it is, so LogApiWriteActivity does not add its
        // own vaguer entry.
        ActivityLogService::record('created', ($complete ? 'Transferred ' : 'Saved a draft transfer of ').$this->describe($transfer), $transfer, ['source' => 'api']);

        return $this->accountTransferRepository->loadShown($transfer);
    }

    public function update(Workspace $workspace, int $id, User $user, array $data): AccountTransfer
    {
        $transfer = $this->draft($workspace, $id, 'A completed transfer cannot be changed. Void it and make a new one instead.');
        $columns  = $this->columns($workspace, $data, false);

        DB::transaction(function () use ($workspace, $user, $transfer, $columns) {
            $this->accountTransferRepository->update($transfer, [...$columns, 'updated_by' => $user->id]);
            $this->accountLedger->rewriteTransfer($workspace, $transfer);
        });

        ActivityLogService::record('updated', 'Updated the draft transfer of '.$this->describe($transfer), $transfer, ['source' => 'api']);

        return $this->accountTransferRepository->loadShown($transfer);
    }

    /** A draft carried out: checked as it is now, then the money moves. */
    public function complete(Workspace $workspace, int $id, User $user): AccountTransfer
    {
        $transfer = $this->draft($workspace, $id, 'Only a draft transfer can be completed.');

        $this->columns($workspace, $transfer->only(['from_account_id', 'to_account_id', 'transfer_date', 'amount', 'amount_received']), true);

        DB::transaction(function () use ($user, $transfer) {
            $this->accountTransferRepository->update($transfer, ['status' => AccountTransfer::COMPLETED, 'updated_by' => $user->id]);
            $this->accountLedger->setStatus($transfer, AccountTransfer::COMPLETED);
        });

        ActivityLogService::record('updated', 'Transferred '.$this->describe($transfer), $transfer, ['source' => 'api']);

        return $this->accountTransferRepository->loadShown($transfer);
    }

    /** A completed transfer taken back: both its rows stay in the histories, marked void. */
    public function void(Workspace $workspace, int $id, User $user, ?string $reason): AccountTransfer
    {
        $transfer = $this->find($workspace, $id);

        if ($transfer->status !== AccountTransfer::COMPLETED) {
            $this->refuse('Only a completed transfer can be voided. A draft is deleted instead.');
        }

        DB::transaction(function () use ($user, $reason, $transfer) {
            $this->accountTransferRepository->update($transfer, ['status' => AccountTransfer::VOID, 'void_reason' => $reason, 'voided_at' => now(), 'updated_by' => $user->id]);
            $this->accountLedger->setStatus($transfer, AccountTransfer::VOID);
        });

        ActivityLogService::record('updated', 'Voided the transfer of '.$this->describe($transfer), $transfer, ['source' => 'api']);

        return $this->accountTransferRepository->loadShown($transfer);
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $transfer = $this->draft($workspace, $id, 'A completed transfer cannot be deleted. Void it instead.');

        DB::transaction(function () use ($transfer) {
            $this->accountLedger->remove($transfer);
            $this->accountTransferRepository->delete($transfer);
        });

        ActivityLogService::record('deleted', 'Deleted the draft transfer of '.$this->describe($transfer), $transfer, ['source' => 'api']);
    }

    /**
     * The transfer's columns as the table holds them: what arrived - the same
     * amount between accounts in one currency - the rate between the two, and
     * the value in the base currency. $complete checks the money may move now.
     */
    private function columns(Workspace $workspace, array $data, bool $complete): array
    {
        $from = $this->account($workspace, (int) $data['from_account_id'], 'from_account_id');
        $to   = $this->account($workspace, (int) $data['to_account_id'], 'to_account_id');

        if ($complete) {
            $today = $this->businessCalendar->today($workspace)->toDateString();

            $this->accountLedger->checkMove($from, $data['transfer_date'], $today, 'transfer_date');
            $this->accountLedger->checkMove($to, $data['transfer_date'], $today, 'transfer_date');
        }

        $amount   = Decimal::round((string) $data['amount']);
        $received = $from->currency === $to->currency ? $amount : (empty($data['amount_received']) ? null : Decimal::round((string) $data['amount_received']));

        if ($received === null) {
            throw ValidationException::withMessages(['amount_received' => "Enter what arrived in {$to->name}, in {$to->currency}."]);
        }

        $base = $this->accountLedger->baseAmount($workspace, $amount, $from->currency, $received, $to->currency, $data['transfer_date']);

        if ($base === null) {
            throw ValidationException::withMessages(['amount' => "Enter an exchange rate for {$from->currency} or {$to->currency} under Settings > Currencies first, so the transfer has a value in {$workspace->base_currency}."]);
        }

        return [
            'from_account_id' => $from->id,
            'to_account_id'   => $to->id,
            'transfer_date'   => $data['transfer_date'],
            'amount'          => $amount,
            'amount_received' => $received,
            'exchange_rate'   => bcdiv($received, $amount, 8),
            'base_amount'     => $base,
            ...array_intersect_key($data, ['reference' => true, 'notes' => true]),
        ];
    }

    private function account(Workspace $workspace, int $id, string $field): PaymentAccount
    {
        $account = $this->paymentAccountRepository->findFor($workspace, $id);

        if (! $account?->is_active) {
            throw ValidationException::withMessages([$field => 'Choose one of your active payment accounts.']);
        }

        return $account;
    }

    private function draft(Workspace $workspace, int $id, string $refusal): AccountTransfer
    {
        $transfer = $this->find($workspace, $id);

        if ($transfer->status !== AccountTransfer::DRAFT) {
            $this->refuse($refusal);
        }

        return $transfer;
    }

    /** What the log calls it: "USD 500.00 from Cash to City Bank". */
    private function describe(AccountTransfer $transfer): string
    {
        $this->accountTransferRepository->loadShown($transfer);

        return "{$transfer->fromAccount->currency} {$transfer->amount} from {$transfer->fromAccount->name} to {$transfer->toAccount->name}";
    }
}
