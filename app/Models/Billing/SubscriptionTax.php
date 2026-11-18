<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Model;

/**
 * The tax izSuite adds to a business's subscription invoices for a country
 * (Doc 13 section 16, Doc 15 section 16), from a day on - VAT 15% for
 * Bangladesh, say. With `reverse_charge`, a buyer that gives its tax ID is
 * charged none: the invoice prints the note instead, and the buyer accounts
 * for the tax itself. Not one of the businesses' own taxes.
 */
class SubscriptionTax extends Model
{
    /** Printed on a reverse-charge invoice when the rule gives no note of its own. */
    public const REVERSE_CHARGE_NOTE = 'Reverse charge: the customer accounts for the tax.';

    protected $fillable = [
        'country',
        'name',
        'rate',
        'starts_on',
        'reverse_charge',
        'reverse_charge_note',
    ];

    protected $casts    = [
        'rate'           => 'decimal:3',
        'starts_on'      => 'date',
        'reverse_charge' => 'boolean',
    ];

    /** The rate as people read it: "15", "7.5". */
    public function percent(): string
    {
        return self::readable((string) $this->rate);
    }

    /** A rate of three decimals as people read it: "15.000" is "15", "7.500" is "7.5". */
    public static function readable(string $rate): string
    {
        return str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate;
    }
}
