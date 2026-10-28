<?php

namespace App\Services\Api\PaymentAccounts;

use App\Models\User\AccountTransaction;
use App\Models\User\AccountTransfer;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Repositories\User\AccountTransactionRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The payment accounts' history as money moves (Doc 05 sections 10 to 14):
 * the row a payment or refund writes, the two a transfer writes, and their
 * status as those are reversed, completed or voided. Only completed rows count
 * towards a recorded balance.
 *
 * Also what money moving through an account must respect: an active account,
 * a date no earlier than its opening balance and no later than today, and a
 * value in the base currency for the Dashboard and reports (section 14).
 */
class AccountLedger
{
    public function __construct(
        protected AccountTransactionRepository $accountTransactionRepository,
        protected ExchangeRateService $exchangeRateService,
    ) {}

    /** The row a customer payment (money in) or refund (money out) writes. */
    public function recordPayment(Workspace $workspace, SalesPayment $payment): void
    {
        $refund = $payment->isRefund();

        $this->accountTransactionRepository->create($workspace, [
            'payment_account_id' => $payment->payment_account_id,
            'transaction_date'   => $payment->payment_date,
            'type'               => $refund ? 'customer_refund' : 'customer_payment',
            'direction'          => $refund ? AccountTransaction::OUT : AccountTransaction::IN,
            'amount'             => $payment->amount,
            'base_amount'        => $payment->base_amount,
            'status'             => AccountTransaction::COMPLETED,
            'contact_id'         => $payment->contact_id,
            'location_id'        => $payment->location_id,
            'sales_document_id'  => $payment->sales_document_id,
            'source_type'        => $payment->getMorphClass(),
            'source_id'          => $payment->id,
            'reference'          => $payment->number ?? $payment->reference,
            'created_by'         => $payment->created_by,
        ]);
    }

    /** The two rows a transfer writes - out of one account, into the other - in the transfer's status. */
    public function recordTransfer(Workspace $workspace, AccountTransfer $transfer): void
    {
        foreach ([['transfer_out', $transfer->from_account_id, $transfer->amount], ['transfer_in', $transfer->to_account_id, $transfer->amount_received]] as [$type, $accountId, $amount]) {
            $this->accountTransactionRepository->create($workspace, [
                'payment_account_id' => $accountId,
                'transaction_date'   => $transfer->transfer_date,
                'type'               => $type,
                'direction'          => AccountTransaction::TYPES[$type],
                'amount'             => $amount,
                'base_amount'        => $transfer->base_amount,
                'status'             => $transfer->status,
                'source_type'        => $transfer->getMorphClass(),
                'source_id'          => $transfer->id,
                'reference'          => $transfer->reference,
                'created_by'         => $transfer->updated_by ?? $transfer->created_by,
            ]);
        }
    }

    /** A draft transfer changed: its rows are written again. */
    public function rewriteTransfer(Workspace $workspace, AccountTransfer $transfer): void
    {
        $this->accountTransactionRepository->deleteFor($transfer);
        $this->recordTransfer($workspace, $transfer);
    }

    /** Every row $source wrote takes $status: reversed, void, completed. */
    public function setStatus(Model $source, string $status): void
    {
        $this->accountTransactionRepository->updateFor($source, ['status' => $status]);
    }

    public function remove(Model $source): void
    {
        $this->accountTransactionRepository->deleteFor($source);
    }

    /**
     * Money may move through $account on $date only while it is active, on or
     * after the date of its opening balance, and not later than today - a
     * recorded payment has happened. Refused against $field.
     */
    public function checkMove(PaymentAccount $account, string $date, string $today, string $field = 'payment_date'): void
    {
        if (! $account->is_active) {
            throw ValidationException::withMessages(['payment_account_id' => "{$account->name} is inactive. Choose an active account."]);
        }

        if ($account->opening_balance_date && $date < $account->opening_balance_date) {
            throw ValidationException::withMessages([$field => "{$account->name}'s opening balance is dated {$account->opening_balance_date}. Use a date on or after it, or change that date first."]);
        }

        if ($date > $today) {
            throw ValidationException::withMessages([$field => 'Money is recorded once it has moved: use today or an earlier date.']);
        }
    }

    /**
     * $amount of $currency in the base currency, as of $date: as it is when it
     * is the base currency; the other side of the exchange - $otherAmount of
     * $otherCurrency - when that one is; else at the rate entered for $date
     * (Settings > Currencies), or at $fallbackRate (base per unit of
     * $otherCurrency) when there is none. Null when nothing gives a rate.
     */
    public function baseAmount(Workspace $workspace, string $amount, string $currency, string $otherAmount, string $otherCurrency, string $date, ?string $fallbackRate = null): ?string
    {
        if ($currency === $workspace->base_currency) {
            return Decimal::round($amount);
        }

        if ($otherCurrency === $workspace->base_currency) {
            return Decimal::round($otherAmount);
        }

        if ($rate = $this->exchangeRateService->rateOn($workspace, $currency, $date)) {
            return Decimal::round(bcmul($amount, $rate, 10));
        }

        if ($rate = $this->exchangeRateService->rateOn($workspace, $otherCurrency, $date) ?? $fallbackRate) {
            return Decimal::round(bcmul($otherAmount, $rate, 10));
        }

        return null;
    }
}
