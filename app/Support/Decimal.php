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

    /**
     * A number as a database sum returns it - "150.00" from MySQL, 150.5 or
     * 5.0E-17 from SQLite, null for no rows - as a decimal string bcmath takes.
     */
    public static function of(mixed $value): string
    {
        $text = (string) ($value ?? '0');

        return preg_match('/^-?\d+(\.\d+)?$/', $text) ? $text : sprintf('%.10F', (float) $text);
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
