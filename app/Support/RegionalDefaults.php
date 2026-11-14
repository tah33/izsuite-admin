<?php

namespace App\Support;

/**
 * The regional settings a country's businesses usually keep (Doc 11 section
 * 5): how they write dates, times and numbers, the day their week starts,
 * their paper and the month their fiscal year starts - and the currency they
 * pay in. A recommendation the owner may change - not a legal rule; Country
 * Localization brings those.
 *
 * Since Doc 15 Phase F3a each country keeps its own in the countries table
 * (Admin > Countries), as its current version has them. The rules below are
 * what that table started from (migration 000145). A country not on the list
 * keeps the base.
 */
final class RegionalDefaults
{
    public const BASE = [
        'date_format'             => 'd M Y',
        'time_format'             => '24h',
        'number_format'           => 'comma_dot',
        'week_start'              => 'monday',
        'paper_size'              => 'a4',
        'fiscal_year_start_month' => 1,
    ];

    /** Where a country's businesses differ from the base. */
    private const COUNTRIES = [
        'US' => ['date_format' => 'm/d/Y', 'time_format' => '12h', 'week_start' => 'sunday', 'paper_size' => 'letter'],
        'CA' => ['date_format' => 'Y-m-d', 'time_format' => '12h', 'week_start' => 'sunday', 'paper_size' => 'letter'],
        'MX' => ['date_format' => 'd/m/Y', 'week_start' => 'sunday', 'paper_size' => 'letter'],
        'PH' => ['date_format' => 'm/d/Y', 'time_format' => '12h', 'week_start' => 'sunday', 'paper_size' => 'letter'],
        'BD' => ['date_format' => 'd/m/Y', 'time_format' => '12h', 'number_format' => 'lakh', 'week_start' => 'saturday', 'fiscal_year_start_month' => 7],
        'IN' => ['date_format' => 'd/m/Y', 'time_format' => '12h', 'number_format' => 'lakh', 'fiscal_year_start_month' => 4],
        'NP' => ['date_format' => 'Y-m-d', 'number_format' => 'lakh', 'week_start' => 'sunday', 'fiscal_year_start_month' => 7],
        'PK' => ['date_format' => 'd/m/Y', 'time_format' => '12h', 'fiscal_year_start_month' => 7],
        'LK' => ['date_format' => 'd/m/Y', 'fiscal_year_start_month' => 4],
        'GB' => ['date_format' => 'd/m/Y', 'fiscal_year_start_month' => 4],
        'IE' => ['date_format' => 'd/m/Y'],
        'AU' => ['date_format' => 'd/m/Y', 'time_format' => '12h', 'fiscal_year_start_month' => 7],
        'NZ' => ['date_format' => 'd/m/Y', 'time_format' => '12h', 'fiscal_year_start_month' => 4],
        'JP' => ['date_format' => 'Y-m-d', 'week_start' => 'sunday', 'fiscal_year_start_month' => 4],
        'CN' => ['date_format' => 'Y-m-d'],
        'KR' => ['date_format' => 'Y-m-d', 'week_start' => 'sunday'],
        'SA' => ['date_format' => 'd/m/Y', 'week_start' => 'sunday'],
        'AE' => ['date_format' => 'd/m/Y'],
        'EG' => ['date_format' => 'd/m/Y', 'week_start' => 'saturday', 'fiscal_year_start_month' => 7],
        'KE' => ['date_format' => 'd/m/Y', 'fiscal_year_start_month' => 7],
        'NG' => ['date_format' => 'd/m/Y'],
        'ZA' => ['date_format' => 'Y-m-d', 'number_format' => 'space_comma', 'week_start' => 'sunday', 'fiscal_year_start_month' => 3],
        'BR' => ['date_format' => 'd/m/Y', 'number_format' => 'dot_comma', 'week_start' => 'sunday'],
    ];

    /** Countries that write 1.234.567,89 - or, the second list, 1 234 567,89 - with days first. */
    private const DOT_COMMA = ['DE', 'AT', 'IT', 'ES', 'NL', 'BE', 'PT', 'DK', 'GR', 'TR', 'ID', 'VN', 'AR', 'CL', 'CO'];

    private const SPACE_COMMA = ['FR', 'SE', 'NO', 'FI', 'PL', 'CZ', 'SK', 'HU', 'RU', 'UA'];

    /** What a business in $country - a code - is recommended to keep: its country's, as its current version has them. */
    public static function for(?string $country): array
    {
        $kept = Countries::find($country);

        return $kept ? array_merge(self::BASE, array_intersect_key($kept, self::BASE)) : self::BASE;
    }

    /** The currency $country - a code - pays in, as its current version has it. Null for none. */
    public static function currency(?string $country): ?string
    {
        return Countries::find($country)['currency'] ?? null;
    }

    /** The settings $country - a code - started with (migration 000145): the rules above. */
    public static function builtIn(?string $country): array
    {
        $country = strtoupper((string) $country);

        return [
            ...self::BASE,
            ...(in_array($country, self::DOT_COMMA, true) ? ['date_format' => 'd/m/Y', 'number_format' => 'dot_comma'] : []),
            ...(in_array($country, self::SPACE_COMMA, true) ? ['date_format' => 'd/m/Y', 'number_format' => 'space_comma'] : []),
            ...(self::COUNTRIES[$country] ?? []),
        ];
    }

    /**
     * The currency $country - a code - started with: the one ICU knows it
     * pays in, BDT for BD, EUR for DE. Null for a country it has none for.
     */
    public static function builtInCurrency(?string $country): ?string
    {
        if (! preg_match('/^[A-Z]{2}$/', strtoupper((string) $country))) {
            return null;
        }

        $code = (new \NumberFormatter('en_'.strtoupper($country), \NumberFormatter::CURRENCY))->getTextAttribute(\NumberFormatter::CURRENCY_CODE);

        return is_string($code) && preg_match('/^[A-Z]{3}$/', $code) && $code !== 'XXX' ? $code : null;
    }
}
