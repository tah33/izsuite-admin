<?php

namespace App\Enums;

/**
 * Where an expense stands (Doc 10 section 9): a draft, recorded - numbered,
 * counted and, until it is paid, owed to its payee - or void. A recorded
 * expense is never deleted (section 10).
 */
enum ExpenseStatus: string
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
