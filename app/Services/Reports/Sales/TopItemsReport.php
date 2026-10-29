<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Top-Selling Items (Doc 07 section 7): what sold most, by issued invoices
 * less credit notes, tax left out, in the base currency. A line typed in
 * without an item counts under its own name. A ranking of the past, not a
 * trend or forecast. An item's row opens the item, with its related
 * transactions (Doc 04 section 20). It may be narrowed to one item category.
 */
class TopItemsReport extends Report
{
    private const LIMIT = 100;

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];
        $docs = [];

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $type) {
            foreach ($this->sales->issuedLines($workspace, $type, $filters['from'], $filters['to'], $filters) as $line) {
                $key = $line->item_id ? "item:{$line->item_id}" : 'line:'.mb_strtolower($line->name);
                $net = $this->figures->lineNet($line);

                $rows[$key] ??= ['item' => $line->name, 'quantity' => '0', 'invoices' => 0, 'net_sales' => '0.00', 'credit_quantity' => '0', 'credit_amount' => '0.00', 'net' => '0.00', 'link' => $line->item_id ? "/items/{$line->item_id}" : null];

                if ($type === DocumentType::Invoice) {
                    $rows[$key]['quantity']  = bcadd($rows[$key]['quantity'], Decimal::of($line->quantity), 3);
                    $rows[$key]['net_sales'] = $this->add($rows[$key]['net_sales'], $net);
                    $docs[$key][$line->sales_document_id] = true;
                } else {
                    $rows[$key]['credit_quantity'] = bcadd($rows[$key]['credit_quantity'], Decimal::of($line->quantity), 3);
                    $rows[$key]['credit_amount']   = $this->add($rows[$key]['credit_amount'], $net);
                }

                $rows[$key]['invoices'] = count($docs[$key] ?? []);
                $rows[$key]['net']      = $this->subtract($rows[$key]['net_sales'], $rows[$key]['credit_amount']);
            }
        }

        uasort($rows, fn (array $a, array $b) => bccomp($b['net'], $a['net'], 2) ?: strcmp($a['item'], $b['item']));

        $rows = array_slice(array_values($rows), 0, self::LIMIT);

        return new ReportTable(
            title: 'Top-Selling Items',
            period: $this->between($filters).(isset($filters['category']) ? " · {$filters['category']->name} items" : ''),
            columns: [
                $this->column('item', 'Item'),
                $this->column('quantity', 'Quantity Sold', 'number'),
                $this->column('invoices', 'Invoices', 'number'),
                $this->column('net_sales', 'Net Sales', 'money', $base),
                $this->column('credit_quantity', 'Credit Quantity', 'number'),
                $this->column('credit_amount', 'Credit Amount', 'money', $base),
                $this->column('net', 'Net After Credits', 'money', $base),
            ],
            rows: $rows,
            totals: ['item' => 'Total', ...$this->totals($rows, ['net_sales', 'credit_amount', 'net'])],
            notes: ["Ranked by net sales less credit notes, in {$base}, tax left out. The top ".self::LIMIT.' show.'],
        );
    }
}
