<?php

namespace App\Services\Reports\Pos;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Estimated POS Margin (Doc 18 section 18.36 - Phase H2): by item, what POS's
 * completed sales of the period sold less what came back - the quantity, and
 * sales before tax in the base currency - less the item's purchase price now
 * times that quantity: the margin, and its share of the sales. An item
 * without a purchase price shows none, and the totals leave it out. An
 * estimate, not a Profit and Loss Statement. Each row opens its item.
 */
class PosMarginReport extends PosReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];

        foreach ($this->lineRows($workspace, $filters, 'item') as $row) {
            $quantity = bcsub($row['quantity'], $row['returned_quantity'], 3);
            $cost     = $row['unit_cost'] === null ? null : Decimal::round(bcmul((string) $row['unit_cost'], $quantity, 10));
            $rows[]   = [
                'group'     => $row['group'],
                'quantity'  => $quantity,
                'net'       => $row['net'],
                'cost'      => $cost,
                'margin'    => $cost === null ? null : $this->subtract($row['net'], $cost),
                'percent'   => $cost === null ? null : $this->percent($this->subtract($row['net'], $cost), $row['net']),
                'link'      => $row['link'],
            ];
        }

        usort($rows, fn (array $a, array $b) => bccomp($b['net'], $a['net'], 2) ?: strcmp($a['group'], $b['group']));
        $costed = array_values(array_filter($rows, fn (array $row) => $row['cost'] !== null));
        $totals = $this->totals($costed, ['net', 'cost', 'margin']);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Estimated POS Margin',
            period: $this->scope($filters).(isset($filters['category']) ? " · {$filters['category']->name} items" : ''),
            columns: [
                $this->column('group', 'Item'),
                $this->column('quantity', 'Net Quantity', 'number'),
                $this->column('net', 'Net Sales', 'money', $base),
                $this->column('cost', 'Estimated Cost', 'money', $base),
                $this->column('margin', 'Estimated Margin', 'money', $base),
                $this->column('percent', 'Margin %', 'percent'),
            ],
            rows: $rows,
            totals: ['group' => count($costed) < count($rows) ? 'Total of items with a cost' : 'Total', ...$totals, 'percent' => $this->percent($totals['margin'], $totals['net'])],
            notes: array_values(array_filter([
                self::ONCE." Sales before tax, in {$base}, less returns. Each item's cost is its purchase price now times the quantity: an estimate, not a Profit and Loss Statement.",
                count($costed) < count($rows) ? 'Items without a purchase price show no cost or margin, and the totals leave them out.' : null,
            ])),
        );
    }
}
