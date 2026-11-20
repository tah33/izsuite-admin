<?php

namespace App\Services\Reports\Pos;

use App\Models\User\Workspace;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PosReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Reports\Report;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesFigures;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

/**
 * One of POS's reports (Doc 18 section 18.36 - Phase H): its completed sales
 * - dated as their invoices - and its returns - dated as their credit notes
 * - read by PosReportRepository, at the user's locations and narrowed to a
 * location, register or cashier. Draft, held, pending and cancelled orders
 * never count, and a completed one counts once (18.38).
 */
abstract class PosReport extends Report
{
    /** The note every POS report about sales ends with. */
    protected const ONCE = 'Completed POS sales count once - held, pending and cancelled orders never - each in the base currency at its own rate, and their returns take off what they credit.';

    /** The groupings by period: every one of the range shows, a quiet one too. */
    protected const PERIODS = ['day', 'week', 'month'];

    public function __construct(
        SalesReportRepository $sales,
        SalesFigures $figures,
        ReportPeriod $period,
        PurchaseReportRepository $purchases,
        ExpenseReportRepository $expenses,
        protected PosReportRepository $pos,
        protected BusinessCalendar $calendar,
    ) {
        parent::__construct($sales, $figures, $period, $purchases, $expenses);
    }

    /** "Ayesha Rahman", or $none. */
    protected function person(?string $first, ?string $last, string $none = 'Removed'): string
    {
        return trim("{$first} {$last}") ?: $none;
    }

    /** $amount, in its document's currency at $rate, in the base currency. */
    protected function base(string $amount, string $rate): string
    {
        return Decimal::round(bcmul($amount, $rate, 10));
    }

    /** The hour of the business's day $moment fell in: "09". */
    protected function hourOf(Workspace $workspace, mixed $moment): string
    {
        return CarbonImmutable::parse($moment)->setTimezone($this->calendar->timezone($workspace))->format('H');
    }

    /** The period a report covers, and what it is narrowed to. */
    protected function scope(array $filters): string
    {
        return $this->between($filters)
            .(isset($filters['register']) ? " · {$filters['register']->name} ({$filters['register']->code})" : '')
            .(isset($filters['cashier']) ? " · {$filters['cashier']->name}" : '');
    }

    /**
     * @return array{0: string, 1: string, 2: int|string} where a sale or return falls grouped $by: its key, label, and
     *                                                     where it sorts - a period by its document's date, an hour by
     *                                                     when it was made, the salesperson its sale's
     */
    protected function groupOf(Workspace $workspace, array $filters, string $by, object $record, mixed $moment): array
    {
        if (in_array($by, self::PERIODS, true)) {
            $key = $this->period->bucket((string) $record->document_date, $by, $filters['week_start'] ?? null);

            return [$key, $this->period->label($key, $by, $filters['date_format'] ?? null), $key];
        }

        if ($by === 'hour') {
            $hour = $this->hourOf($workspace, $moment);

            return ["hour:{$hour}", "{$hour}:00 - {$hour}:59", (int) $hour];
        }

        return match ($by) {
            'location'    => ['location:'.$record->location_id, $record->location_name ?? 'Removed', 0],
            'register'    => ['register:'.$record->pos_register_id, $record->register_name ? "{$record->register_name} ({$record->register_code})" : 'Removed', 0],
            'cashier'     => ['cashier:'.($record->cashier_id ?? 0), $this->person($record->cashier_first_name, $record->cashier_last_name), 0],
            'salesperson' => ['salesperson:'.($record->salesperson_id ?? 0), $record->salesperson_id ? $this->person($record->salesperson_first_name, $record->salesperson_last_name) : 'No salesperson', 0],
        };
    }

    /** Grouped by period, a row for each of the range - $blank with its label and where it sorts; none otherwise. */
    protected function periodRows(array $filters, string $by, array $blank): array
    {
        if (! in_array($by, self::PERIODS, true)) {
            return [];
        }

        $rows = [];

        foreach ($this->period->buckets($filters['from'], $filters['to'], $by, $filters['week_start'] ?? null) as $key) {
            $rows[$key] = ['group' => $this->period->label($key, $by, $filters['date_format'] ?? null), 'order' => $key, ...$blank];
        }

        return $rows;
    }

    /** Periods and hours in their order; any other group by $by - biggest first - then by name. */
    protected function sorted(array $rows, string $by, string $biggest): array
    {
        uasort($rows, fn (array $a, array $b) => in_array($by, [...self::PERIODS, 'hour'], true)
            ? $a['order'] <=> $b['order']
            : (bccomp((string) $b[$biggest], (string) $a[$biggest], 2) ?: strcmp($a['group'], $b['group'])));

        return array_values(array_map(fn (array $row) => array_diff_key($row, ['order' => true]), $rows));
    }

    /** $total over $count, to the cent - none of no orders. */
    protected function averaged(string $total, int|string $count): ?string
    {
        return bccomp((string) $count, '0', 3) > 0 ? Decimal::round(bcdiv($total, (string) $count, 4)) : null;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the first and last moments of the period's business days, in UTC (Phase H2) */
    protected function instants(Workspace $workspace, array $filters): array
    {
        $zone = $this->calendar->timezone($workspace);

        return [CarbonImmutable::parse($filters['from'], $zone)->startOfDay()->utc(), CarbonImmutable::parse($filters['to'], $zone)->endOfDay()->utc()];
    }

    /** The business's day $moment fell on: "2026-11-19". */
    protected function dayOf(Workspace $workspace, mixed $moment): string
    {
        return CarbonImmutable::parse($moment)->setTimezone($this->calendar->timezone($workspace))->toDateString();
    }

    /**
     * The period's item lines sold less those returned, grouped $by item,
     * category or brand - an item by the name it sold under - with their
     * quantities, sales before tax after each line's share of its sale's
     * discount, returns, and an item's purchase price now.
     */
    protected function lineRows(Workspace $workspace, array $filters, string $by): array
    {
        $rows  = [];
        $sides = ['sold' => $this->pos->saleLines($workspace, $filters['from'], $filters['to'], $filters), 'returned' => $this->pos->returnLines($workspace, $filters['from'], $filters['to'], $filters)];

        foreach ($sides as $side => $lines) {
            foreach ($lines as $line) {
                [$key, $label] = match ($by) {
                    'item'     => ["item:{$line->item_id}", $line->name],
                    'category' => ['category:'.($line->category_id ?? 0), $line->category_name ?? 'No category'],
                    'brand'    => ['brand:'.($line->brand_id ?? 0), $line->brand_name ?? 'No brand'],
                };
                $rows[$key] ??= ['group' => $label, 'quantity' => '0.000', 'net_sales' => '0.00', 'returned_quantity' => '0.000', 'returns' => '0.00', 'unit_cost' => $line->purchase_price, 'link' => $by === 'item' ? "/items/{$line->item_id}" : null];
                $net = $this->figures->lineNet($line);

                if ($side === 'sold') {
                    $rows[$key]['quantity']  = bcadd($rows[$key]['quantity'], (string) $line->quantity, 3);
                    $rows[$key]['net_sales'] = $this->add($rows[$key]['net_sales'], $net);
                } else {
                    $rows[$key]['returned_quantity'] = bcadd($rows[$key]['returned_quantity'], (string) $line->quantity, 3);
                    $rows[$key]['returns']           = $this->add($rows[$key]['returns'], $net);
                }
            }
        }

        return array_map(fn (array $row) => [...$row, 'net' => $this->subtract($row['net_sales'], $row['returns'])], $rows);
    }

    /** The one currency $rows are in - none when they are in several, or there are none (Phase H3). */
    protected function oneCurrency(array $rows): ?string
    {
        $currencies = array_values(array_unique(array_column($rows, 'currency')));

        return count($currencies) === 1 ? $currencies[0] : null;
    }

    /** An amount read from a joined table, to the cent - "45.00" - whatever the database gave. */
    protected function cents(mixed $amount): string
    {
        return Decimal::round(Decimal::of($amount));
    }
}
