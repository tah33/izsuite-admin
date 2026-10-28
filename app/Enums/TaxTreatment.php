<?php

namespace App\Enums;

/**
 * How a tax rate treats what it is applied to (Doc 11 section 10). Only a
 * standard rate charges a percentage; the others are 0%, kept apart because
 * reports show zero-rated, exempt and out-of-scope amounts separately (Doc 07
 * section 16).
 */
enum TaxTreatment: string
{
    case Standard = 'standard';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';
    case OutOfScope = 'out_of_scope';
    case NoTax = 'no_tax';

    public function label(): string
    {
        return match ($this) {
            self::Standard   => 'Standard',
            self::ZeroRated  => 'Zero-Rated',
            self::Exempt     => 'Exempt',
            self::OutOfScope => 'Out of Scope',
            self::NoTax      => 'No Tax',
        };
    }

    /** Whether a rate of this kind has a percentage of its own. */
    public function charges(): bool
    {
        return $this === self::Standard;
    }
}
