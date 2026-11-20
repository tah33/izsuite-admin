<?php

namespace App\Services\Reports\Pos;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * POS Sales Summary and POS's "Sales by" reports (Doc 18 section 18.36 -
 * Phase H1): its completed sales before tax, with their tax, in the base
 * currency, less their returns - grouped by day, week or month (every one of
 * the range), location, register, cashier, salesperson or hour of the day,
 * a sale's own and a return's where and when it was made, but for the
 * salesperson, its sale's - or, line by line, by item, category or brand.
 * Periods and hours in their order, any other group biggest first. Each
 * "Sales by" report is this one grouped one way (ReportCatalog::GROUPED).
 */
class PosSalesByReport extends PosReport
{
    /** Each grouping's column heading. */
    public const GROUPS = [
        'day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'location' => 'Location', 'register' => 'Register', 'cashier' => 'Cashier',
        'salesperson' => 'Salesperson', 'hour' => 'Hour', 'item' => 'Item', 'category' => 'Category', 'brand' => 'Brand',
    ];

    private const BY_LINE = ['item', 'category', 'brand'];

    private const BLANK = ['orders' => 0, 'net_sales' => '0.00', 'tax' => '0.00', 'total' => '0.00', 'returns' => '0.00', 'returns_tax' => '0.00'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        // Another report's grouping - a customer, a supplier - is by day, as the summary is unless told.
        $by   = array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'day';
        $base = $workspace->base_currency;

        if (in_array($by, self::BY_LINE, true)) {
            return $this->byLine($workspace, $filters, $by, $base);
        }

        $rows = $this->periodRows($filters, $by, self::BLANK);

        foreach ($this->pos->sales($workspace, $filters['from'], $filters['to'], $filters) as $sale) {
            [$key, $label, $order] = $this->groupOf($workspace, $filters, $by, $sale, $sale->completed_at);
            $rows[$key] ??= ['group' => $label, 'order' => $order, ...self::BLANK];
            $rows[$key]['orders']++;
            $rows[$key]['net_sales'] = $this->add($rows[$key]['net_sales'], (string) $sale->base_net_total);
            $rows[$key]['tax']       = $this->add($rows[$key]['tax'], (string) $sale->base_tax_total);
            $rows[$key]['total']     = $this->add($rows[$key]['total'], (string) $sale->base_grand_total);
        }

        foreach ($this->pos->returns($workspace, $filters['from'], $filters['to'], $filters) as $return) {
            [$key, $label, $order] = $this->groupOf($workspace, $filters, $by, $return, $return->created_at);
            $rows[$key] ??= ['group' => $label, 'order' => $order, ...self::BLANK];
            $rows[$key]['returns']     = $this->add($rows[$key]['returns'], (string) $return->base_net_total);
            $rows[$key]['returns_tax'] = $this->add($rows[$key]['returns_tax'], (string) $return->base_tax_total);
        }

        $rows   = $this->sorted(array_map(fn (array $row) => $this->finished($row), $rows), $by, 'net');
        $totals = $this->totals($rows, ['net_sales', 'tax', 'total', 'returns', 'net', 'net_tax']);
        $orders = array_sum(array_column($rows, 'orders'));
        $taxed  = $this->taxesShown($workspace);

        return new ReportTable(
            title: $filters['report_name'] ?? 'POS Sales Summary',
            period: $this->scope($filters),
            columns: array_values(array_filter([
                $this->column('group', self::GROUPS[$by]),
                $this->column('orders', 'Orders', 'number'),
                $this->column('net_sales', 'Sales Before Tax', 'money', $base),
                $taxed ? $this->column('tax', 'Tax', 'money', $base) : null,
                $this->column('total', 'Total', 'money', $base),
                $this->column('average', 'Average Order', 'money', $base),
                $this->column('returns', 'Returns', 'money', $base),
                $this->column('net', 'Net Sales', 'money', $base),
                $taxed ? $this->column('net_tax', 'Net Tax', 'money', $base) : null,
            ])),
            rows: array_map(fn (array $row) => array_diff_key($row, ['returns_tax' => true]), $rows),
            totals: ['group' => 'Total', 'orders' => $orders, ...$totals, 'average' => $this->averaged($totals['total'], $orders)],
            summary: ($filters['report_key'] ?? null) === 'pos-sales-summary' ? [
                ['label' => 'Sales Before Tax', 'value' => $totals['net_sales'], 'currency' => $base],
                ['label' => 'Returns', 'value' => $totals['returns'], 'currency' => $base],
                ['label' => 'Net Sales', 'value' => $totals['net'], 'currency' => $base],
                ['label' => 'Average Order', 'value' => $this->averaged($totals['total'], $orders) ?? '0.00', 'currency' => $base],
            ] : [],
            notes: array_values(array_filter([
                self::ONCE.' Net Sales is before tax: sales less returns.',
                $by === 'hour' ? 'Hours are the business\'s own: a sale\'s when it was completed, a return\'s when it was made.' : null,
                $by === 'salesperson' ? 'A return counts for the salesperson of its sale.' : null,
            ])),
            chart: in_array($by, [...self::PERIODS, 'hour'], true) ? ['label' => 'group', 'value' => 'net'] : null,
        );
    }

    /** A row's net sales and tax - less its returns' - and its average order. */
    private function finished(array $row): array
    {
        return [
            ...$row,
            'average' => $this->averaged($row['total'], $row['orders']),
            'net'     => $this->subtract($row['net_sales'], $row['returns']),
            'net_tax' => $this->subtract($row['tax'], $row['returns_tax']),
        ];
    }

    /** Item, category or brand: each sale's item lines, less its returns' - by quantity and before tax. */
    private function byLine(Workspace $workspace, array $filters, string $by, string $base): ReportTable
    {
        $rows = array_map(fn (array $row) => array_diff_key($row, ['unit_cost' => true]), array_values($this->lineRows($workspace, $filters, $by)));
        usort($rows, fn (array $a, array $b) => bccomp($b['net'], $a['net'], 2) ?: strcmp($a['group'], $b['group']));

        return new ReportTable(
            title: $filters['report_name'] ?? 'POS Sales Summary',
            period: $this->scope($filters),
            columns: [
                $this->column('group', self::GROUPS[$by]),
                $this->column('quantity', 'Quantity Sold', 'number'),
                $this->column('net_sales', 'Sales Before Tax', 'money', $base),
                $this->column('returned_quantity', 'Quantity Returned', 'number'),
                $this->column('returns', 'Returns', 'money', $base),
                $this->column('net', 'Net Sales', 'money', $base),
            ],
            rows: $rows,
            totals: ['group' => 'Total', ...$this->totals($rows, ['net_sales', 'returns', 'net'])],
            notes: [self::ONCE.' Lines are before tax, after their share of the sale\'s own discount; a category or brand is the item\'s now.'],
        );
    }
}
