<?php

namespace App\Enums;

/**
 * Where a purchase stands (Doc 09 section 9): a draft, recorded - numbered,
 * counted and, until it is paid, owed to its supplier - or void. A recorded
 * purchase is never deleted (section 10).
 */
enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Recorded = 'recorded';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft    => 'Draft',
            self::Recorded => 'Recorded',
            self::Void     => 'Void',
        };
    }
}
