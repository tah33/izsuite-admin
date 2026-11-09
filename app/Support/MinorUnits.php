<?php

namespace App\Support;

/**
 * Amounts in a currency's smallest unit, as card providers count them: USD
 * 22.43 is 2243, JPY 500 is 500 (a currency without cents).
 */
final class MinorUnits
{
    private const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public static function of(string $amount, string $currency): int
    {
        return self::whole($currency) ? (int) round((float) $amount) : (int) bcmul($amount, '100', 0);
    }

    /** Back to an amount as invoices keep it: 2243 USD is "22.43". */
    public static function toDecimal(int $minor, string $currency): string
    {
        return self::whole($currency) ? $minor.'.00' : bcdiv((string) $minor, '100', 2);
    }

    private static function whole(string $currency): bool
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true);
    }
}
