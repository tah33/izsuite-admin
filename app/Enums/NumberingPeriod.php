<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Whether a document number carries a period - INV-2026-00001 - and starts
 * again from the first number with each new one (Doc 11 section 21's "Year or
 * Month Component" and "Reset Rule" in one: a number that restarts without its
 * period in it would repeat an earlier one).
 */
enum NumberingPeriod: string
{
    case None = 'none';
    case Year = 'year';
    case FinancialYear = 'financial_year';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::None          => 'None',
            self::Year          => 'Calendar Year',
            self::FinancialYear => 'Financial Year',
            self::Month         => 'Month',
        };
    }

    /**
     * The part of a number dated $date that names its period: "2026",
     * "2026-27" (a financial year starting in July), "2026-10". Null for none.
     * A financial year starting in January is the calendar year.
     */
    public function key(CarbonInterface $date, int $financialYearStart): ?string
    {
        if ($this === self::FinancialYear && $financialYearStart > 1) {
            $first = $date->month >= $financialYearStart ? $date->year : $date->year - 1;

            return $first.'-'.substr((string) ($first + 1), -2);
        }

        return match ($this) {
            self::None                      => null,
            self::Year, self::FinancialYear => $date->format('Y'),
            self::Month                     => $date->format('Y-m'),
        };
    }
}
