<?php

namespace App\Services\Reports;

use App\Models\User\Workspace;
use App\Services\Shared\BusinessCalendar;
use Carbon\CarbonImmutable;

/**
 * The dates Dashboard and Reports work in (Doc 02 section 2, Doc 07 section
 * 19): a range picked by name - This Month unless told otherwise - or given,
 * in the business's own time zone; and the day, week or month its figures are
 * grouped by, which follows the range's length (Doc 02 section 6).
 *
 * A week starts on Monday. Periods are keyed by their first day ("2026-10-26").
 */
class ReportPeriod
{
    public const RANGES = ['today', 'this_week', 'this_month', 'this_quarter', 'this_year', 'custom'];

    public const GRAINS = ['day', 'week', 'month'];

    public function __construct(
        protected BusinessCalendar $businessCalendar,
    ) {}

    /** @return array{0: string, 1: string} the first and last day, as Y-m-d */
    public function range(Workspace $workspace, ?string $range, ?string $from, ?string $to): array
    {
        $today = $this->businessCalendar->today($workspace);

        if ($from && $to) {
            return [$from, $to];
        }

        return match ($range ?? 'this_month') {
            'today'        => [$today->toDateString(), $today->toDateString()],
            'this_week'    => [$today->startOfWeek(CarbonImmutable::MONDAY)->toDateString(), $today->endOfWeek(CarbonImmutable::SUNDAY)->toDateString()],
            'this_quarter' => [$today->firstOfQuarter()->toDateString(), $today->lastOfQuarter()->toDateString()],
            'this_year'    => [$today->startOfYear()->toDateString(), $today->endOfYear()->toDateString()],
            default        => [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()],
        };
    }

    public function today(Workspace $workspace): string
    {
        return $this->businessCalendar->today($workspace)->toDateString();
    }

    /** Days for up to a month, weeks for up to a quarter, months beyond. */
    public function grain(string $from, string $to): string
    {
        $days = CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;

        return $days <= 31 ? 'day' : ($days <= 92 ? 'week' : 'month');
    }

    /** The first day of the $grain $date falls in. */
    public function bucket(string $date, string $grain): string
    {
        $day = CarbonImmutable::parse($date);

        return match ($grain) {
            'week'  => $day->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            'month' => $day->startOfMonth()->toDateString(),
            default => $day->toDateString(),
        };
    }

    /** @return list<string> every period from $from to $to, so a quiet one still shows */
    public function buckets(string $from, string $to, string $grain): array
    {
        $keys = [];
        $day  = CarbonImmutable::parse($this->bucket($from, $grain));
        $end  = CarbonImmutable::parse($to);

        while ($day <= $end && count($keys) < 400) {
            $keys[] = $day->toDateString();
            $day    = match ($grain) {
                'week'  => $day->addWeek(),
                'month' => $day->addMonth(),
                default => $day->addDay(),
            };
        }

        return $keys;
    }

    /** @return array{0: string, 1: string} the first and last day of the $grain that starts on $key, within $from and $to */
    public function bounds(string $key, string $grain, string $from, string $to): array
    {
        $start = CarbonImmutable::parse($key);
        $end   = match ($grain) {
            'week'  => $start->endOfWeek(CarbonImmutable::SUNDAY),
            'month' => $start->endOfMonth(),
            default => $start,
        };

        return [max($key, $from), min($end->toDateString(), $to)];
    }

    /** "28 Oct 2026", "Week of 26 Oct 2026", "Oct 2026". */
    public function label(string $key, string $grain): string
    {
        $day = CarbonImmutable::parse($key);

        return match ($grain) {
            'week'  => 'Week of '.$day->format('j M Y'),
            'month' => $day->format('M Y'),
            default => $day->format('j M Y'),
        };
    }
}
