<?php

namespace App\Services\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Support\Decimal;

/**
 * What a sales document is and may do next (Doc 08 sections 7 to 16), given
 * the business's today ("2026-10-28"). The API refuses what is not listed
 * here, and sends the list so the page shows only those actions (Doc 14
 * section 14.7: "All status transitions are validated by the backend"). What
 * an invoice was paid and credited comes from its own columns, which
 * InvoiceSettlement keeps up to date.
 *
 * A draft or sent quotation or proforma past its expiry date reads as
 * expired: it can still be edited - to give it a later date - converted or
 * cancelled, but no longer sent, accepted or rejected. A converted one can be
 * converted again, which the page asks to confirm.
 */
final class SalesDocumentRules
{
    /** draft, sent, accepted, rejected, expired, converted, cancelled, issued or void. */
    public function status(SalesDocument $document, string $today): string
    {
        $status = $document->status;

        if ($document->type->isOffer() && in_array($status, [SalesDocumentStatus::Draft, SalesDocumentStatus::Sent], true)
            && $document->expiry_date && $document->expiry_date < $today) {
            return SalesDocumentStatus::EXPIRED;
        }

        return $status->value;
    }

    /** Whether it has a balance to show: an issued invoice's still to pay, an issued credit note's credit left. */
    public function holdsBalance(SalesDocument $document): bool
    {
        return $document->status === SalesDocumentStatus::Issued
            && in_array($document->type, [DocumentType::Invoice, DocumentType::CreditNote], true);
    }

    /** An issued invoice past its due date with something still to pay (Doc 08 section 7). */
    public function overdue(SalesDocument $document, string $today): bool
    {
        return $document->type === DocumentType::Invoice
            && $document->status === SalesDocumentStatus::Issued
            && $document->due_date && $document->due_date < $today
            && bccomp((string) $document->balance_due, '0', 2) > 0;
    }

    /** @return list<string> */
    public function actions(SalesDocument $document, string $today): array
    {
        $status  = $this->status($document, $today);
        // To a sales order only with the full Sales app, which the API checks (Doc 16 Phase B).
        $convert = $document->type === DocumentType::Quotation
            ? ['convert_to_proforma', 'convert_to_sales_order', 'convert_to_invoice']
            : ['convert_to_sales_order', 'convert_to_invoice'];
        $answer  = $document->type === DocumentType::Quotation ? ['accept', 'reject'] : [];

        if ($document->type === DocumentType::Invoice) {
            return match ($status) {
                'draft'  => ['edit', 'issue', 'delete'],
                'issued' => $this->invoiceActions($document),
                default  => [],
            };
        }

        if ($document->type === DocumentType::SalesOrder) {
            return $this->orderActions($document);
        }

        // A credit note refunds what it has left, and is voided only before
        // any of it was refunded (Doc 08 section 15).
        if ($document->type === DocumentType::CreditNote) {
            return match ($status) {
                'draft'  => ['edit', 'issue', 'delete'],
                'issued' => array_values(array_filter([
                    $this->positive($this->refundable($document)) ? 'refund' : null,
                    Decimal::isZero((string) $document->refunded_amount) ? 'void' : null,
                ])),
                default  => [],
            };
        }

        return match ($status) {
            'draft'     => ['edit', 'send', ...$convert, 'delete'],
            'sent'      => ['edit', 'send', ...$answer, ...$convert, 'cancel'],
            'expired'   => ['edit', ...$convert, $document->isDraft() ? 'delete' : 'cancel'],
            'accepted'  => [...$convert, 'cancel'],
            'converted' => $convert,
            default     => [],
        };
    }

    public function allows(SalesDocument $document, string $action, string $today): bool
    {
        return in_array($action, $this->actions($document, $today), true);
    }

    /**
     * How it may reach the customer (Doc 06 section 15, Doc 08 sections 8 and
     * 20): `email` - a draft quotation or proforma is sent by emailing it -
     * `reminder` for an issued invoice that still owes, and `share`, a secure
     * link, once it is numbered and until it is voided or cancelled.
     *
     * @return list<string>
     */
    public function delivery(SalesDocument $document, string $today): array
    {
        $status = $this->status($document, $today);
        $email  = match (true) {
            $document->type->isOffer()                     => in_array($status, ['draft', 'sent', 'accepted', 'converted'], true),
            $document->type === DocumentType::SalesOrder => in_array($status, ['confirmed', 'on_hold', 'completed'], true),
            default                                        => $status === 'issued',
        };

        return array_values(array_filter([
            $email ? 'email' : null,
            $document->type === DocumentType::Invoice && $status === 'issued' && $this->positive((string) $document->balance_due) ? 'reminder' : null,
            $document->number && ! in_array($status, ['draft', 'void', 'cancelled'], true) ? 'share' : null,
        ]));
    }

    public function delivers(SalesDocument $document, string $way, string $today): bool
    {
        return in_array($way, $this->delivery($document, $today), true);
    }

    /**
     * A sales order's (Doc 16 section 16.9): a draft is changed, confirmed or
     * deleted. Confirming locks it: a confirmed one is amended with a reason,
     * held, completed or cancelled, and invoices (Phase B2) and a proforma
     * invoice made from it; a
     * held one is amended, resumed or cancelled. Any can be duplicated. It is
     * e-mailed and shared once confirmed (delivery()).
     *
     * @return list<string>
     */
    private function orderActions(SalesDocument $order): array
    {
        return match ($order->status) {
            SalesDocumentStatus::Draft     => ['edit', 'confirm', 'duplicate', 'delete'],
            SalesDocumentStatus::Confirmed => ['create_invoice', 'amend', 'hold', 'complete', 'create_proforma', 'duplicate', 'cancel'],
            SalesDocumentStatus::OnHold    => ['amend', 'resume', 'duplicate', 'cancel'],
            default                        => ['duplicate'],
        };
    }

    /** What is still left to credit on an invoice: its total less its issued credit notes. */
    public function creditable(SalesDocument $invoice): string
    {
        return bcsub((string) $invoice->grand_total, (string) $invoice->credited_amount, 2);
    }

    /**
     * What an issued credit note may still refund (Doc 08 section 16): what it
     * has left, but no more than the cash its invoice took and has not paid
     * back - withheld tax went to the tax office, not to the business.
     * Expects its source, the invoice, loaded.
     */
    public function refundable(SalesDocument $note): string
    {
        $invoice = $note->source;
        $cash    = $invoice ? bcsub((string) $invoice->amount_paid, (string) $invoice->refunded_amount, 2) : '0.00';

        return $this->positive($cash) ? Decimal::min((string) $note->balance_due, $cash) : '0.00';
    }

    /**
     * An issued invoice's (Doc 08 section 8): a payment while it owes, a credit
     * note while any of it is left to credit, a refund while it holds cash and
     * there is credit for it - left on a credit note, or still to be made - and
     * void only before anything settled it.
     */
    private function invoiceActions(SalesDocument $invoice): array
    {
        $creditable = $this->positive($this->creditable($invoice));
        $cash       = bcsub((string) $invoice->amount_paid, (string) $invoice->refunded_amount, 2);
        $settledBy  = Decimal::sum([$invoice->amount_paid, $invoice->withholding_amount, $invoice->credited_amount]);
        $leftOver   = bcsub(bcsub($settledBy, (string) $invoice->refunded_amount, 2), (string) $invoice->grand_total, 2);

        return array_values(array_filter([
            $this->positive((string) $invoice->balance_due) ? 'record_payment' : null,
            $creditable ? 'credit_note' : null,
            $this->positive($cash) && ($creditable || $this->positive($leftOver)) ? 'refund' : null,
            Decimal::isZero($settledBy) ? 'void' : null,
        ]));
    }

    private function positive(string $amount): bool
    {
        return bccomp($amount, '0', 2) > 0;
    }
}
