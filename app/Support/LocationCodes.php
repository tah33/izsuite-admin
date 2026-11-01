<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A location's code (Doc 12 section 17): short, upper case, letters, digits
 * and dashes, unique within its business. One is made from a location's name
 * where none was given - "Main Location" is MAIN, "Dhaka Branch" DHAKA - and
 * numbered on when its business has that code already: MAIN2.
 */
final class LocationCodes
{
    public const MAX = 10;

    /** What a code may hold, typed in any case. */
    public const PATTERN = '/^[A-Za-z0-9-]+$/';

    /** @param  list<string>  $taken  the business's codes already in use, in any case */
    public static function from(string $name, array $taken = []): string
    {
        $first = explode(' ', trim(Str::ascii($name)))[0];
        $base  = substr(strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $first)), 0, 6) ?: 'LOC';
        $used  = array_map('strtoupper', $taken);
        $code  = $base;

        for ($n = 2; in_array($code, $used, true); $n++) {
            $code = substr($base, 0, self::MAX - strlen((string) $n)).$n;
        }

        return $code;
    }
}
