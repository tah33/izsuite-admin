<?php

namespace App\Services\Reports\Pos;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Top-Selling Items (Doc 18 section 18.36 - Phase H2): what POS's completed
 * sales of the period sold most - by quantity, less what came back - with
 * their sales before tax and each one's share of them, in the base currency.
 * A ranking of the past. It may be narrowed to one item category. Each row
 * opens its item.
 */
class PosTopItemsReport extends PosReport
{
    private const LIMIT = 100;

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base  = $workspace->base_currency;
        $rows  = array_map(fn (array $row) => [...array_diff_key($row, ['unit_cost' => true]), 'net_quantity' => bcsub($row['quantity'], $row['returned_quantity'], 3)], array_values($this->lineRows($workspace, $filters, 'item')));
        $whole = $this->totals($rows, ['net'])['net'];

        usort($rows, fn (array $a, array $b) => bccomp($b['net_quantity'], $a['net_quantity'], 3) ?: (bccomp($b['net'], $a['net'], 2) ?: strcmp($a['group'], $b['group'])));
        $rows = array_map(fn (array $row, int $at) => ['rank' => $at + 1, ...$row, 'share' => $this->percent($row['net'], $whole)], array_slice($rows, 0, self::LIMIT), array_keys(array_slice($rows, 0, self::LIMIT)));

        return new ReportTable(
            title: $filters['report_name'] ?? 'Top-Selling Items',
            period: $this->scope($filters).(isset($filters['category']) ? " · {$filters['category']->name} items" : ''),
            columns: [
                $this->column('rank', '#', 'number'),
                $this->column('group', 'Item'),
                $this->column('quantity', 'Quantity Sold', 'number'),
                $this->column('returned_quantity', 'Quantity Returned', 'number'),
                $this->column('net_quantity', 'Net Quantity', 'number'),
                $this->column('net', 'Net Sales', 'money', $base),
                $this->column('share', 'Share of Sales', 'percent'),
            ],
            rows: $rows,
            totals: ['group' => 'Total', ...$this->totals($rows, ['net'])],
            notes: [self::ONCE.' Ranked by the quantity sold less returned, then by net sales: before tax, after each line\'s share of its sale\'s discount. The top '.self::LIMIT.' show.'],
        );
    }
}
