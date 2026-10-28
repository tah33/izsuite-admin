<?php

namespace App\Services\Api\Sales;

use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesPaymentRepository;
use App\Support\Decimal;

/**
 * What an issued invoice still owes, worked out again from everything that
 * settles it (Doc 08 sections 5, 13, 15 and 16) - after every payment,
 * withholding, credit note, refund and reversal, inside the transaction that
 * made the change:
 *
 * - its completed payments, in cash and in tax withheld, come off first;
 * - then its issued credit notes, in the order they were issued: each takes
 *   off what is still owed, up to what it has not refunded, and keeps the rest
 *   available - which in Sales Lite can only be refunded (section 25: no
 *   carrying credit to other invoices);
 * - money refunded out of a credit note was paid back, so it no longer covers
 *   anything.
 *
 * Balance due is never below zero: an invoice paid and then credited shows
 * the credit as available on its credit note instead. Payment status follows
 * (Doc 08 section 7): paid once nothing is owed - refunded when all its cash
 * went back - and partly paid once anything settled it.
 */
class InvoiceSettlement
{
    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesPaymentRepository $salesPaymentRepository,
    ) {}

    public function settle(SalesDocument $invoice): void
    {
        $notes = $this->salesDocumentRepository->issuedCreditNotesOf($invoice);
        $moves = $this->salesPaymentRepository->completedFor([$invoice->id, ...$notes->pluck('id')->all()]);

        $payments  = $moves->where('kind', SalesPayment::PAYMENT)->where('sales_document_id', $invoice->id);
        $paid      = Decimal::sum($payments->pluck('applied_amount'));
        $withheld  = Decimal::sum($payments->pluck('withholding_amount'));
        $remaining = bcsub(bcsub((string) $invoice->grand_total, $paid, 2), $withheld, 2);

        $credited = $refunded = $applied = '0.00';

        foreach ($notes as $note) {
            $noteRefunded = Decimal::sum($moves->where('kind', SalesPayment::REFUND)->where('sales_document_id', $note->id)->pluck('applied_amount'));
            $capacity     = bcsub((string) $note->grand_total, $noteRefunded, 2);
            $noteApplied  = Decimal::min($capacity, bccomp($remaining, '0', 2) > 0 ? $remaining : '0.00');
            $available    = bcsub($capacity, $noteApplied, 2);
            $remaining    = bcsub($remaining, $noteApplied, 2);

            $this->salesDocumentRepository->update($note, [
                'applied_amount'  => $noteApplied,
                'refunded_amount' => $noteRefunded,
                'balance_due'     => $available,
                'payment_status'  => $this->creditStatus($noteApplied, $noteRefunded, $available),
            ]);

            $credited = bcadd($credited, (string) $note->grand_total, 2);
            $refunded = bcadd($refunded, $noteRefunded, 2);
            $applied  = bcadd($applied, $noteApplied, 2);
        }

        $balance = bccomp($remaining, '0', 2) > 0 ? $remaining : '0.00';

        $this->salesDocumentRepository->update($invoice, [
            'amount_paid'        => $paid,
            'withholding_amount' => $withheld,
            'credited_amount'    => $credited,
            'refunded_amount'    => $refunded,
            'balance_due'        => $balance,
            'payment_status'     => $this->paymentStatus($balance, $paid, Decimal::sum([$withheld, $applied]), $refunded),
        ]);
    }

    /** unpaid, partially_paid, paid or refunded. */
    private function paymentStatus(string $balance, string $paid, string $otherwiseSettled, string $refunded): string
    {
        if (bccomp($balance, '0', 2) === 0) {
            return bccomp($refunded, '0', 2) > 0 && bccomp($paid, $refunded, 2) <= 0 ? 'refunded' : 'paid';
        }

        return bccomp(bcadd($paid, $otherwiseSettled, 2), '0', 2) > 0 ? 'partially_paid' : SalesDocument::PAYMENT_UNPAID;
    }

    /** A credit note's: available, partially_applied, fully_applied or refunded. */
    private function creditStatus(string $applied, string $refunded, string $available): string
    {
        if (bccomp($available, '0', 2) > 0) {
            return bccomp($applied, '0', 2) > 0 ? 'partially_applied' : 'available';
        }

        return bccomp($refunded, '0', 2) > 0 ? 'refunded' : 'fully_applied';
    }
}
