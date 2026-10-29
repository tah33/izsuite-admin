<?php

namespace App\Support;

/**
 * What a bank's account number and bank code are called in its country (Doc
 * 05 section 7.2: "Fields adapt to the selected country"): an IBAN where the
 * country writes accounts as one, a sort code in the UK, a routing number in
 * the US, an IFSC code in India, a BSB in Australia, a transit number in
 * Canada - otherwise an account number and a SWIFT / BIC code. The form's
 * labels follow the same list (utils/bankLabels.ts in the frontend).
 */
final class BankLabels
{
    private const CODES = [
        'AU' => 'BSB',
        'CA' => 'Transit Number',
        'GB' => 'Sort Code',
        'IN' => 'IFSC Code',
        'US' => 'Routing Number',
    ];

    /** The IBAN registry's countries - the UK, which pays at home by sort code, aside. */
    private const IBAN = [
        'AD', 'AE', 'AL', 'AT', 'AZ', 'BA', 'BE', 'BG', 'BH', 'BR', 'CH', 'CR', 'CY', 'CZ', 'DE', 'DK', 'DO', 'EE', 'EG', 'ES',
        'FI', 'FO', 'FR', 'GE', 'GI', 'GL', 'GR', 'GT', 'HR', 'HU', 'IE', 'IL', 'IQ', 'IS', 'IT', 'JO', 'KW', 'KZ', 'LB', 'LC',
        'LI', 'LT', 'LU', 'LV', 'MC', 'MD', 'ME', 'MK', 'MR', 'MT', 'MU', 'NL', 'NO', 'PK', 'PL', 'PS', 'PT', 'QA', 'RO', 'RS',
        'SA', 'SC', 'SE', 'SI', 'SK', 'SM', 'ST', 'SV', 'TL', 'TN', 'TR', 'UA', 'VA', 'VG', 'XK',
    ];

    /** @return array{number: string, code: string} */
    public static function for(?string $country): array
    {
        return [
            'number' => in_array($country, self::IBAN, true) ? 'IBAN' : 'Account Number',
            'code'   => self::CODES[$country] ?? 'SWIFT / BIC',
        ];
    }
}
