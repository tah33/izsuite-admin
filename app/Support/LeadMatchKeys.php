<?php

namespace App\Support;

/**
 * What a lead is compared on to find its possible duplicates (Doc 17
 * section 17.21 - Phase C2), tidied as Doc 03's contacts are compared
 * (ContactDuplicates): an e-mail and a business name whole, ignoring case,
 * and a phone number by its last nine digits - "+880 1711-000000" and
 * "01711000000" are the same number - not at all when it has fewer than
 * seven. Kept on the lead, so the comparison is a lookup.
 */
final class LeadMatchKeys
{
    /**
     * The keys of a lead with these fields.
     *
     * @return array{match_email: ?string, match_phone: ?string, match_business: ?string}
     */
    public static function of(?string $email, ?string $phone, ?string $business): array
    {
        return [
            'match_email'    => self::text($email),
            'match_phone'    => self::phone($phone),
            'match_business' => self::text($business),
        ];
    }

    public static function text(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) < 7 ? null : substr($digits, -9);
    }
}
