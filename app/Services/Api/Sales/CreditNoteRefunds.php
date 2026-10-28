<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesPaymentRepository;
use App\Services\Api\PaymentAccounts\AccountLedger;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer refunds (Doc 08 section 16): money paid back out of a payment
 * account against the credit left on an issued credit note. A refund on a
 * paid invoice is made the same way - its credit note first, then the refund.
 *
 * A refund is at most what the credit note may still refund (see
 * SalesDocumentRules::refundable), leaves the account as money out, and is
 * reversed - never deleted - when it was a mistake (SalesPaymentService).
 */
class CreditNoteRefunds
{
    use RefusesConflicts;

    public function __construct(
        protected SalesPaymentRepository $salesPaymentRepository,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected SalesDocumentService $salesDocumentService,
        protected SalesDocumentRules $salesDocumentRules,
        protected InvoiceSettlement $invoiceSettlement,
        protected AccountLedger $accountLedger,
    ) {}

    /** $data is validated (see RecordRefundRequest). */
    public function refund(Workspace $workspace, int $noteId, User $user, array $data): SalesPayment
    {
        $note  = $this->salesDocumentService->find($workspace, DocumentType::CreditNote, $noteId);
        $today = $this->salesDocumentService->today($workspace);

        if (! $this->salesDocumentRules->allows($note, 'refund', $today)) {
            $this->refuse('There is nothing left on this credit note to refund.');
        }

        $account = $this->paymentAccountRepository->findFor($workspace, $data['payment_account_id']);

        $this->accountLedger->checkMove($account, $data['payment_date'], $today);

        if ($data['payment_date'] < $note->document_date) {
            throw ValidationException::withMessages(['payment_date' => "A refund cannot come before its credit note, dated {$note->document_date}."]);
        }

        $amount  = Decimal::round((string) $data['amount']);
        $same    = $account->currency === $note->currency;
        $applied = $same ? $amount : (empty($data['applied_amount']) ? null : Decimal::round((string) $data['applied_amount']));

        if ($applied === null) {
            throw ValidationException::withMessages(['applied_amount' => "Enter what this refund takes off the credit note, in {$note->currency}."]);
        }

        $refundable = $this->salesDocumentRules->refundable($note);

        if (bccomp($applied, $refundable, 2) > 0) {
            throw ValidationException::withMessages([$same ? 'amount' : 'applied_amount' => "At most {$note->currency} {$refundable} can be refunded from this credit note."]);
        }

        $invoice = $this->salesDocumentRepository->findIssuedInvoiceFor($workspace, (int) $note->source_id);

        $refund = DB::transaction(function () use ($workspace, $user, $data, $note, $invoice, $account, $amount, $applied) {
            $refund = $this->salesPaymentRepository->create($workspace, [
                'kind'               => SalesPayment::REFUND,
                'sales_document_id'  => $note->id,
                'contact_id'         => $note->contact_id,
                'payment_account_id' => $account->id,
                'location_id'        => $note->location_id,
                'payment_date'       => $data['payment_date'],
                'method'             => $data['method'] ?? null,
                'reference'          => $data['reference'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'amount'             => $amount,
                'applied_amount'     => $applied,
                'exchange_rate'      => bcdiv($amount, $applied, 8),
                'base_amount'        => $this->accountLedger->baseAmount($workspace, $amount, $account->currency, $applied, $note->currency, $data['payment_date'], (string) $note->exchange_rate),
                'balance_after'      => bcsub((string) $note->balance_due, $applied, 2),
                'created_by'         => $user->id,
            ]);

            $this->accountLedger->recordPayment($workspace, $refund);
            $this->invoiceSettlement->settle($invoice);

            return $refund;
        });

        // Logged here, by what it is, so LogApiWriteActivity does not add its
        // own vaguer entry.
        ActivityLogService::record('created', "Refunded {$account->currency} {$amount} from credit note {$note->number}", $refund, ['source' => 'api']);

        return $this->salesPaymentRepository->loadShown($refund);
    }
}
