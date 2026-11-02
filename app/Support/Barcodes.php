<?php

namespace App\Support;

/**
 * The barcode formats a business may hold its items' barcodes to (Doc 11
 * section 18): any code at all, or an EAN-13 or UPC-A - all digits, the last
 * the GS1 check digit, so a mistyped or misscanned one is caught.
 */
final class Barcodes
{
    /** The digits of each checked format, and what it is called. */
    private const FORMATS = [
        'ean13' => [13, 'EAN-13'],
        'upca'  => [12, 'UPC-A'],
    ];

    /** Whether $code is a barcode of $format - any code is, for 'any'. */
    public static function valid(string $code, string $format): bool
    {
        if (! isset(self::FORMATS[$format])) {
            return true;
        }

        if (! preg_match('/^\d{'.self::FORMATS[$format][0].'}$/', $code)) {
            return false;
        }

        // From the digit before the check digit leftwards, weighted 3, 1, 3, 1...
        $digits = array_map('intval', str_split($code));
        $check  = array_pop($digits);
        $sum    = 0;

        foreach (array_reverse($digits) as $position => $digit) {
            $sum += $digit * ($position % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === $check;
    }

    /** What a barcode that is not of $format is told. */
    public static function rule(string $format): string
    {
        [$digits, $name] = self::FORMATS[$format];

        return "Enter an {$name} barcode: {$digits} digits, the last its check digit.";
    }
}
