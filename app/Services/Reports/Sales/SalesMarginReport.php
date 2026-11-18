<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Estimated Sales Margin (Doc 16 section 16.26 - Phase H2b): by item, what
 * issued invoices of the period sold less what credit notes took back - the
 * quantity, and sales before tax in the base currency - less the item's
 * purchase price now times that quantity: the margin, and its share of the
 * sales. Only items count; a line typed in without one, like delivery, has
 * no cost. An item without a purchase price shows none, and the totals leave
 * it out. An estimate, not a Profit and Loss Statement, which is
 * Accounting's. Each row opens its item.
 */
class SalesMarginReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $type) {
            foreach ($this->sales->issuedLines($workspace, $type, $filters['from'], $filters['to'], $filters) as $line) {
                if (! $line->item_id) {
                    continue;
                }

                $sold     = $type === DocumentType::Invoice;
                $quantity = Decimal::of($line->quantity);
                $net      = $this->figures->lineNet($line);

                $rows[$line->item_id] ??= ['item' => $line->name, 'quantity' => '0', 'net_sales' => '0.00', 'cost' => null, 'margin' => null, 'percent' => null, 'unit_cost' => $line->purchase_price, 'link' => "/items/{$line->item_id}"];
                $rows[$line->item_id]['quantity']  = $sold ? bcadd($rows[$line->item_id]['quantity'], $quantity, 3) : bcsub($rows[$line->item_id]['quantity'], $quantity, 3);
                $rows[$line->item_id]['net_sales'] = $sold ? $this->add($rows[$line->item_id]['net_sales'], $net) : $this->subtract($rows[$line->item_id]['net_sales'], $net);
            }
        }

        $rows = array_map(fn (array $row) => array_diff_key($this->costed($row), ['unit_cost' => true]), $rows);

        uasort($rows, fn (array $a, array $b) => bccomp($b['net_sales'], $a['net_sales'], 2) ?: strcmp($a['item'], $b['item']));

        $rows   = array_values($rows);
        $costed = array_values(array_filter($rows, fn (array $row) => $row['cost'] !== null));
        $totals = $this->totals($costed, ['net_sales', 'cost', 'margin']);

        return new ReportTable(
            title: 'Estimated Sales Margin',
            period: $this->between($filters).(isset($filters['category']) ? " · {$filters['category']->name} items" : ''),
            columns: [
                $this->column('item', 'Item'),
                $this->column('quantity', 'Quantity Sold', 'number'),
                $this->column('net_sales', 'Net Sales', 'money', $base),
                $this->column('cost', 'Estimated Cost', 'money', $base),
                $this->column('margin', 'Estimated Margin', 'money', $base),
                $this->column('percent', 'Margin %', 'percent'),
            ],
            rows: $rows,
            totals: [
                'item' => count($costed) < count($rows) ? 'Total of items with a cost' : 'Total',
                ...$totals,
                'percent' => $this->percent($totals['margin'], $totals['net_sales']),
            ],
            notes: array_values(array_filter([
                "Sales before tax, in {$base}, less credit notes. Each item's cost is its purchase price now times the quantity: an estimate, not a Profit and Loss Statement.",
                count($costed) < count($rows) ? 'Items without a purchase price show no cost or margin, and the totals leave them out.' : null,
                'Lines without an item, like delivery, are left out.',
            ])),
        );
    }

    /** An item's row with its cost - its purchase price now times the quantity - the margin, and its share of the sales, while it has a price. */
    private function costed(array $row): array
    {
        if ($row['unit_cost'] === null) {
            return $row;
        }

        $cost   = Decimal::round(bcmul(Decimal::of($row['unit_cost']), $row['quantity'], 10));
        $margin = $this->subtract($row['net_sales'], $cost);

        return [...$row, 'cost' => $cost, 'margin' => $margin, 'percent' => $this->percent($margin, $row['net_sales'])];
    }
}
