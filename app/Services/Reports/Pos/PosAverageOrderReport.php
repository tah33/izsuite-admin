<?php

namespace App\Services\Reports\Pos;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Average Order Value (Doc 18 section 18.36 - Phase H1): the period's
 * completed sales, each once at what it came to - with tax, and before it -
 * in the base currency, with the items on it, by day, week or month (every
 * one of the range), location, register or cashier. Returns do not take off
 * an order's value.
 */
class PosAverageOrderReport extends PosReport
{
    /** Each grouping's column heading. */
    public const GROUPS = ['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'location' => 'Location', 'register' => 'Register', 'cashier' => 'Cashier'];

    private const BLANK = ['orders' => 0, 'total' => '0.00', 'net_sales' => '0.00', 'items' => '0.000'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $by     = array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'day';
        $base   = $workspace->base_currency;
        $rows   = $this->periodRows($filters, $by, self::BLANK);
        $placed = [];

        foreach ($this->pos->sales($workspace, $filters['from'], $filters['to'], $filters) as $sale) {
            [$key, $label, $order] = $this->groupOf($workspace, $filters, $by, $sale, $sale->completed_at);
            $placed[$sale->id] = $key;
            $rows[$key] ??= ['group' => $label, 'order' => $order, ...self::BLANK];
            $rows[$key]['orders']++;
            $rows[$key]['total']     = $this->add($rows[$key]['total'], (string) $sale->base_grand_total);
            $rows[$key]['net_sales'] = $this->add($rows[$key]['net_sales'], (string) $sale->base_net_total);
        }

        foreach ($this->pos->saleLines($workspace, $filters['from'], $filters['to'], $filters) as $line) {
            if (isset($placed[$line->pos_order_id])) {
                $key = $placed[$line->pos_order_id];
                $rows[$key]['items'] = bcadd($rows[$key]['items'], (string) $line->quantity, 3);
            }
        }

        $rows   = $this->sorted(array_map(fn (array $row) => $this->averages($row), $rows), $by, 'average');
        $totals = $this->averages(['orders' => array_sum(array_column($rows, 'orders')), ...$this->totals($rows, ['total', 'net_sales']), 'items' => array_reduce($rows, fn (string $sum, array $row) => bcadd($sum, $row['items'], 3), '0.000')]);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Average Order Value',
            period: $this->scope($filters),
            columns: [
                $this->column('group', self::GROUPS[$by]),
                $this->column('orders', 'Orders', 'number'),
                $this->column('total', 'Total', 'money', $base),
                $this->column('average', 'Average Order', 'money', $base),
                $this->column('net_sales', 'Sales Before Tax', 'money', $base),
                $this->column('average_net', 'Average Before Tax', 'money', $base),
                $this->column('items', 'Items Sold', 'number'),
                $this->column('per_order', 'Items per Order', 'number'),
            ],
            rows: $rows,
            totals: ['group' => 'Total', ...$totals],
            summary: [
                ['label' => 'Average Order', 'value' => $totals['average'] ?? '0.00', 'currency' => $base],
                ['label' => 'Average Before Tax', 'value' => $totals['average_net'] ?? '0.00', 'currency' => $base],
            ],
            notes: ['Each completed POS sale counts once, at what it came to, in the base currency at its own rate - held, pending and cancelled orders never. Returns are not taken off an order\'s value. Items are the quantities on its item lines.'],
            chart: in_array($by, self::PERIODS, true) ? ['label' => 'group', 'value' => 'average'] : null,
        );
    }

    /** A row's - or the totals' - average order, with tax and before it, and items per order. */
    private function averages(array $row): array
    {
        return [
            ...$row,
            'average'     => $this->averaged($row['total'], $row['orders']),
            'average_net' => $this->averaged($row['net_sales'], $row['orders']),
            'per_order'   => $this->averaged($row['items'], $row['orders']),
        ];
    }
}
