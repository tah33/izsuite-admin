<?php

namespace App\Services\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;

/**
 * What a sales document is and may do next (Doc 08 sections 7 to 11), given
 * the business's today ("2026-10-28"). The API refuses what is not listed
 * here, and sends the list so the page shows only those actions (Doc 14
 * section 14.7: "All status transitions are validated by the backend").
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
        $convert = $document->type === DocumentType::Quotation ? ['convert_to_proforma', 'convert_to_invoice'] : ['convert_to_invoice'];
        $answer  = $document->type === DocumentType::Quotation ? ['accept', 'reject'] : [];

        if ($document->type === DocumentType::Invoice) {
            return match ($status) {
                'draft'  => ['edit', 'issue', 'delete'],
                'issued' => ['void'],
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
}
