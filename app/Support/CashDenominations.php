<?php

namespace App\Support;

/**
 * The usual notes and coins of the currencies izSuite knows (Doc 18 Phase
 * B2b), the largest first: what a business counting its POS cash by
 * denomination starts with, until it sets its own in Settings → Point of
 * Sale. A currency izSuite does not know starts with none.
 */
final class CashDenominations
{
    private const USUAL = [
        'AED' => ['1000', '500', '200', '100', '50', '20', '10', '5', '1', '0.50', '0.25'],
        'AUD' => ['100', '50', '20', '10', '5', '2', '1', '0.50', '0.20', '0.10', '0.05'],
        'BDT' => ['1000', '500', '200', '100', '50', '20', '10', '5', '2', '1'],
        'CAD' => ['100', '50', '20', '10', '5', '2', '1', '0.25', '0.10', '0.05'],
        'EUR' => ['200', '100', '50', '20', '10', '5', '2', '1', '0.50', '0.20', '0.10', '0.05', '0.02', '0.01'],
        'GBP' => ['50', '20', '10', '5', '2', '1', '0.50', '0.20', '0.10', '0.05', '0.02', '0.01'],
        'INR' => ['500', '200', '100', '50', '20', '10', '5', '2', '1'],
        'JPY' => ['10000', '5000', '2000', '1000', '500', '100', '50', '10', '5', '1'],
        'MYR' => ['100', '50', '20', '10', '5', '1', '0.50', '0.20', '0.10', '0.05'],
        'PKR' => ['5000', '1000', '500', '100', '50', '20', '10', '5', '2', '1'],
        'SAR' => ['500', '200', '100', '50', '10', '5', '2', '1', '0.50', '0.25', '0.10', '0.05'],
        'SGD' => ['1000', '100', '50', '10', '5', '2', '1', '0.50', '0.20', '0.10', '0.05'],
        'USD' => ['100', '50', '20', '10', '5', '2', '1', '0.50', '0.25', '0.10', '0.05', '0.01'],
    ];

    /** The currency's usual notes and coins, each to the cent ("20.00"), the largest first. */
    public static function usual(string $currency): array
    {
        return self::sorted(self::USUAL[$currency] ?? []);
    }

    /** $values as a list keeps them: each to the cent, once, the largest first. */
    public static function sorted(array $values): array
    {
        $values = array_values(array_unique(array_map(fn ($value) => bcadd((string) $value, '0', 2), $values)));

        usort($values, fn (string $a, string $b) => bccomp($b, $a, 2));

        return $values;
    }
}
