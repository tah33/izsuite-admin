<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Models\User\WithholdingTax;
use App\Models\User\Workspace;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\SalesPaymentRepository;
use App\Repositories\User\WithholdingTaxRepository;
use App\Services\Api\PaymentAccounts\AccountLedger;
use App\Services\Documents\DocumentNumbers;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer payments (Doc 08 sections 13 and 14): recorded against an issued
 * invoice, numbered as their receipt (RCT-00001), and reversed - never edited
 * or deleted - when they were a mistake. Payments Received lists them.
 *
 * A payment's money lands in an active payment account, in the account's
 * currency. When that is not the invoice's currency, what the payment took
 * off the invoice is entered too (section 14), and the rate between the two
 * is kept. Withholding tax the customer kept back settles the invoice as well,
 * without reaching the account. Together they never come to more than the
 * invoice still owes: Sales Lite has no overpayments or customer credit
 * (section 13).
 *
 * Every payment and reversal writes the account's history (AccountLedger) and
 * settles the invoice again (InvoiceSettlement), in one transaction.
 */
class SalesPaymentService
{
    use RefusesConflicts;

    public function __construct(
        protected SalesPaymentRepository $salesPaymentRepository,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected WithholdingTaxRepository $withholdingTaxRepository,
        protected SalesDocumentService $salesDocumentService,
        protected SalesDocumentRules $salesDocumentRules,
        protected InvoiceSettlement $invoiceSettlement,
        protected AccountLedger $accountLedger,
        protected DocumentNumbers $documentNumbers,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->salesPaymentRepository->paginatePaymentsFor($workspace, $filters, $perPage);
    }

    /** Another business's payment answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): SalesPayment
    {
        return $this->salesPaymentRepository->findFor($workspace, $id) ?? abort(404, 'Payment not found.');
    }

    /** $data is validated (see RecordPaymentRequest). */
    public function record(Workspace $workspace, int $invoiceId, User $user, array $data): SalesPayment
    {
        $invoice = $this->salesDocumentService->find($workspace, DocumentType::Invoice, $invoiceId);
        $today   = $this->salesDocumentService->today($workspace);

        if (! $this->salesDocumentRules->allows($invoice, 'record_payment', $today)) {
            $this->refuse('Only an issued invoice with something still to pay takes a payment.');
        }

        $account = $this->paymentAccountRepository->findFor($workspace, $data['payment_account_id']);

        $this->accountLedger->checkMove($account, $data['payment_date'], $today);

        if ($data['payment_date'] < $invoice->document_date) {
            throw ValidationException::withMessages(['payment_date' => "A payment cannot come before its invoice, dated {$invoice->document_date}."]);
        }

        $amount      = Decimal::round((string) $data['amount']);
        $applied     = $this->applied($account, $invoice, $amount, $data);
        $withholding = $this->withholding($workspace, $data);

        if (bccomp(bcadd($applied, $withholding['withholding_amount'], 2), (string) $invoice->balance_due, 2) > 0) {
            throw ValidationException::withMessages([
                $account->currency === $invoice->currency ? 'amount' : 'applied_amount' => "That comes to more than the {$invoice->currency} {$invoice->balance_due} still due. Overpayments are not taken in Sales Lite.",
            ]);
        }

        $payment = DB::transaction(function () use ($workspace, $user, $data, $invoice, $account, $amount, $applied, $withholding) {
            $payment = $this->salesPaymentRepository->create($workspace, [
                'kind'               => SalesPayment::PAYMENT,
                'number'             => $this->documentNumbers->next(
                    $workspace,
                    DocumentType::CustomerReceipt,
                    CarbonImmutable::parse($data['payment_date']),
                    fn (string $number) => $this->salesPaymentRepository->receiptNumberTaken($workspace, $number),
                ),
                'sales_document_id'  => $invoice->id,
                'contact_id'         => $invoice->contact_id,
                'payment_account_id' => $account->id,
                'location_id'        => $invoice->location_id,
                'payment_date'       => $data['payment_date'],
                'method'             => $data['method'] ?? null,
                'reference'          => $data['reference'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'amount'             => $amount,
                'applied_amount'     => $applied,
                'exchange_rate'      => bcdiv($amount, $applied, 8),
                'base_amount'        => $this->accountLedger->baseAmount($workspace, $amount, $account->currency, $applied, $invoice->currency, $data['payment_date'], (string) $invoice->exchange_rate),
                ...$withholding,
                'balance_after'      => bcsub(bcsub((string) $invoice->balance_due, $applied, 2), $withholding['withholding_amount'], 2),
                'created_by'         => $user->id,
            ]);

            $this->accountLedger->recordPayment($workspace, $payment);
            $this->invoiceSettlement->settle($invoice);

            return $payment;
        });

        // Logged here, by what it is, so LogApiWriteActivity does not add its
        // own vaguer entry.
        ActivityLogService::record('created', "Recorded payment {$payment->number} of {$account->currency} {$amount} for invoice {$invoice->number}", $payment, ['source' => 'api']);

        return $this->salesPaymentRepository->loadShown($payment);
    }

    /**
     * A payment or refund taken back (Doc 08 sections 13 and 16): it stays on
     * record, marked reversed, and leaves every balance.
     */
    public function reverse(Workspace $workspace, int $id, User $user, ?string $reason): SalesPayment
    {
        $payment = $this->find($workspace, $id);
        $noun    = $payment->isRefund() ? 'refund' : 'payment';

        if (! $payment->isCompleted()) {
            $this->refuse("This {$noun} was reversed already.");
        }

        $invoice = $this->salesDocumentService->find($workspace, DocumentType::Invoice, (int) ($payment->isRefund() ? $payment->document->source_id : $payment->sales_document_id));

        DB::transaction(function () use ($payment, $user, $reason, $invoice) {
            $this->salesPaymentRepository->update($payment, [
                'status'          => SalesPayment::REVERSED,
                'reversed_at'     => now(),
                'reversed_by'     => $user->id,
                'reversal_reason' => $reason,
            ]);

            $this->accountLedger->setStatus($payment, SalesPayment::REVERSED);
            $this->invoiceSettlement->settle($invoice);
        });

        $what = $payment->isRefund() ? "refund of {$payment->account->currency} {$payment->amount} from credit note {$payment->document->number}" : "payment {$payment->number}";

        ActivityLogService::record('updated', "Reversed {$what}", $payment, ['source' => 'api']);

        return $this->salesPaymentRepository->loadShown($payment);
    }

    /**
     * What a payment takes off the invoice, in the invoice's currency: the
     * amount itself in the same currency, or the amount entered for it.
     */
    private function applied(PaymentAccount $account, SalesDocument $invoice, string $amount, array $data): string
    {
        if ($account->currency === $invoice->currency) {
            return $amount;
        }

        if (empty($data['applied_amount'])) {
            throw ValidationException::withMessages(['applied_amount' => "Enter what this payment pays off the invoice, in {$invoice->currency}."]);
        }

        return Decimal::round((string) $data['applied_amount']);
    }

    /**
     * The withholding tax kept back, as it is on the day: only while the
     * business uses withholding tax, from a rate that applies to customer
     * payments and is in effect on the payment's date.
     */
    private function withholding(Workspace $workspace, array $data): array
    {
        if (empty($data['withholding_tax_id'])) {
            return ['withholding_amount' => '0.00'];
        }

        $rate = $this->withholdingTaxRepository->findFor($workspace, $data['withholding_tax_id']);

        if (! $workspace->withholding_tax || ! $rate?->usableOn(WithholdingTax::CUSTOMER_PAYMENTS, $data['payment_date'])) {
            throw ValidationException::withMessages(['withholding_tax_id' => 'Choose an active withholding tax for customer payments, in effect on the payment date.']);
        }

        return [
            'withholding_tax_id' => $rate->id,
            'withholding_name'   => $rate->name,
            'withholding_code'   => $rate->code,
            'withholding_rate'   => $rate->rate,
            'withholding_amount' => Decimal::round((string) $data['withholding_amount']),
        ];
    }
}
