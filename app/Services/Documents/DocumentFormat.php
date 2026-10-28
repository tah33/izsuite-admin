<?php

namespace App\Services\Documents;

use App\Models\Admin\Currency;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

/**
 * Dates, numbers and money as a document's template writes them (Doc 06
 * section 15: "Correct currency formatting", "Correct date and number
 * formatting"): 28 Oct 2026 or 10/28/2026; 1,234,567.89, 1.234.567,89,
 * 1 234 567,89 or the lakh 12,34,567.89; and the currency's symbol where the
 * platform knows it. Amounts stay exact decimal strings throughout.
 */
final class DocumentFormat
{
    /** thousands separator, decimal separator */
    private const SEPARATORS = [
        'comma_dot'   => [',', '.'],
        'dot_comma'   => ['.', ','],
        'space_comma' => ["\u{202F}", ','],
        'lakh'        => [',', '.'],
    ];

    private array $symbols;

    public function __construct(private string $dateFormat, private string $numberFormat)
    {
        $this->symbols = array_map(fn (array $currency) => $currency['symbol'] ?? null, Currency::getAllCached());
    }

    public static function of(array $template): self
    {
        return new self($template['date_format'] ?? 'd M Y', $template['number_format'] ?? 'comma_dot');
    }

    public function date(?string $date): string
    {
        return $date ? CarbonImmutable::parse($date)->format($this->dateFormat) : '';
    }

    /** "$1,234.50", "BDT 1,234.50" where there is no symbol; "−" in front of a negative. */
    public function money(?string $amount, string $currency): string
    {
        $symbol = $this->symbols[$currency] ?? null;
        $value  = $this->number(ltrim((string) ($amount ?? '0'), '-'));
        $sign   = str_starts_with((string) $amount, '-') && ! Decimal::isZero($amount) ? '−' : '';

        return $sign.($symbol ? $symbol.$value : "{$currency} {$value}");
    }

    /** $value to $decimals places, grouped and separated as the template says. */
    public function number(string $value, int $decimals = 2): string
    {
        [$thousands, $point] = self::SEPARATORS[$this->numberFormat] ?? self::SEPARATORS['comma_dot'];
        $negative            = str_starts_with($value, '-');
        [$whole, $fraction]  = array_pad(explode('.', Decimal::round(ltrim($value, '-'), $decimals)), 2, '');

        return ($negative ? '-' : '').$this->group($whole, $thousands).($decimals ? $point.$fraction : '');
    }

    /** A quantity as a person writes it: "2", "1.5". */
    public function quantity(string $value): string
    {
        $plain = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
        [$whole, $fraction] = array_pad(explode('.', $plain), 2, null);
        [$thousands, $point] = self::SEPARATORS[$this->numberFormat] ?? self::SEPARATORS['comma_dot'];

        return $this->group($whole, $thousands).($fraction !== null ? $point.$fraction : '');
    }

    /** Groups of three from the right - or, for the lakh, three and then twos: 12,34,567. */
    private function group(string $whole, string $separator): string
    {
        $groups = [];
        $size   = 3;

        while (strlen($whole) > $size) {
            array_unshift($groups, substr($whole, -$size));
            $whole = substr($whole, 0, -$size);
            $size  = $this->numberFormat === 'lakh' ? 2 : 3;
        }

        array_unshift($groups, $whole);

        return implode($separator, $groups);
    }
}
