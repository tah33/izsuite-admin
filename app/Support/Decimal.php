<?php

namespace App\Support;

/**
 * Money worked out exactly, as decimal strings (Doc 14 section 14.2: "All
 * financial calculations use decimal values, not floating-point values").
 * bcmath only cuts digits off; round() rounds half away from zero, as money is
 * rounded.
 */
final class Decimal
{
    /** "2.345" rounded to $scale places: "2.35". */
    public static function round(string $value, int $scale = 2): string
    {
        $half = $scale > 0 ? '0.'.str_repeat('0', $scale).'5' : '0.5';

        return str_starts_with(ltrim($value), '-') ? bcsub($value, $half, $scale) : bcadd($value, $half, $scale);
    }

    /** Whether $value is zero, however it is written: "0", "0.00". */
    public static function isZero(?string $value): bool
    {
        return $value === null || $value === '' || bccomp($value, '0', 10) === 0;
    }

    public static function min(string $a, string $b): string
    {
        return bccomp($a, $b, 10) <= 0 ? $a : $b;
    }

    /** Sums $values to $scale places - "0.00" for none. */
    public static function sum(iterable $values, int $scale = 2): string
    {
        $total = bcadd('0', '0', $scale);

        foreach ($values as $value) {
            $total = bcadd($total, (string) $value, $scale);
        }

        return $total;
    }
}
