<?php

namespace App\Services\Reports\Sales;

use App\Models\User\SalesDelivery;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Delivery Performance (Doc 16 section 16.26 - Phase H3b): the deliveries
 * that arrived in the period - by the day they did - grouped by month,
 * customer or carrier, each against its order's expected delivery day: on
 * time by it, late after it, or with none to go by. The on-time rate is of
 * those with a day; the average days late, of those late. No values: a
 * delivery note has none.
 */
class DeliveryPerformanceReport extends Report
{
    use GroupsSalesDocuments;

    public const GROUPS = ['month' => 'Month', 'customer' => 'Customer', 'carrier' => 'Carrier'];

    private const COUNTS = ['deliveries', 'on_time', 'late', 'unplanned'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $group = array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'month';
        $rows  = $group === 'month' ? $this->months($filters, fn (string $label) => $this->blank($label)) : [];
        $late  = [];

        foreach ($this->sales->deliveredBetween($workspace, $filters['from'], $filters['to'], $filters) as $delivery) {
            [$key, $label] = $this->place($delivery, $group, $filters);
            $expected      = $delivery->order?->expected_delivery_date;
            $outcome       = ! $expected ? 'unplanned' : ($delivery->delivered_on <= $expected ? 'on_time' : 'late');

            $rows[$key] ??= $this->blank($label);
            $rows[$key]['deliveries']++;
            $rows[$key][$outcome]++;

            if ($outcome === 'late') {
                $late[$key][] = $this->figures->daysOverdue($expected, $delivery->delivered_on);
            }
        }

        if ($group !== 'month') {
            uasort($rows, fn (array $a, array $b) => $b['deliveries'] <=> $a['deliveries'] ?: strcmp($a['group'], $b['group']));
        }

        $totals = ['group' => 'Total', ...array_combine(self::COUNTS, array_map(fn (string $count) => array_sum(array_column($rows, $count)), self::COUNTS))];

        return new ReportTable(
            title: 'Delivery Performance',
            period: $this->between($filters),
            columns: [
                $this->column('group', self::GROUPS[$group]),
                $this->column('deliveries', 'Deliveries', 'number'),
                $this->column('on_time', 'On Time', 'number'),
                $this->column('late', 'Late', 'number'),
                $this->column('unplanned', 'No Expected Day', 'number'),
                $this->column('rate', 'On-Time Rate', 'percent'),
                $this->column('average_late', 'Average Days Late', 'number'),
            ],
            rows: array_values(array_map(fn (array $row, string $key) => $this->rated($row, $late[$key] ?? []), $rows, array_keys($rows))),
            totals: $this->rated($totals, array_merge(...array_values($late))),
            notes: ["Deliveries that arrived in the period, against their order's expected delivery day: on time by it, late after it. The on-time rate is of those with a day to go by."],
        );
    }

    /** @return array{0: string, 1: string} where a delivery falls by $group, and what the report calls it */
    private function place(SalesDelivery $delivery, string $group, array $filters): array
    {
        $carrier = trim((string) $delivery->carrier);

        return match ($group) {
            'customer' => ["contact:{$delivery->order?->contact_id}", $delivery->order ? $this->figures->customerName($delivery->order) : 'Unknown customer'],
            'carrier'  => ['carrier:'.mb_strtolower($carrier), $carrier !== '' ? $carrier : 'No carrier'],
            default    => $this->month($delivery->delivered_on, $filters),
        };
    }

    /** A group's counts with its on-time rate and, from $late's days, its average days late. */
    private function rated(array $row, array $late): array
    {
        return [
            ...$row,
            'rate'         => $this->percent((string) $row['on_time'], (string) ($row['on_time'] + $row['late'])),
            'average_late' => $late ? Decimal::round(bcdiv((string) array_sum($late), (string) count($late), 10), 1) : null,
        ];
    }

    private function blank(string $label): array
    {
        return ['group' => $label, 'deliveries' => 0, 'on_time' => 0, 'late' => 0, 'unplanned' => 0];
    }
}
