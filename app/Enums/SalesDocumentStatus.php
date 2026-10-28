<?php

namespace App\Enums;

/**
 * Where a quotation, proforma invoice or invoice stands (Doc 08 sections 7, 9
 * and 10). A quotation runs draft - sent - accepted or rejected, a proforma
 * draft - sent; either can be converted or cancelled. An invoice runs draft -
 * issued - void.
 *
 * Expired is never stored: a draft or sent quotation or proforma whose expiry
 * date has passed reads as expired (see SalesDocumentRules).
 */
enum SalesDocumentStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
    case Issued = 'issued';
    case Void = 'void';

    public const EXPIRED = 'expired';

    /** The statuses each type can have, as stored - and, for filters, expired. */
    public static function filtersFor(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::Quotation => ['draft', 'sent', 'accepted', 'rejected', self::EXPIRED, 'converted', 'cancelled'],
            DocumentType::Proforma  => ['draft', 'sent', self::EXPIRED, 'converted', 'cancelled'],
            default                 => ['draft', 'issued', 'void'],
        };
    }
}
