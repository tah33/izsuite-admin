<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\PriceListRule;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Price List Usage (Doc 16 section 16.26 - Phase H2b): by price list and
 * rule, the lines of issued invoices in the period that each priced - as the
 * line kept it, whatever the list says now - how many, on how many invoices,
 * their quantity and their sales before tax, in the base currency. A rule
 * removed since has a row of its own; a list removed since takes its lines
 * out. Credit notes are not taken off. Each row opens its list.
 */
class PriceListUsageReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base     = $workspace->base_currency;
        $lines    = $this->sales->issuedLines($workspace, DocumentType::Invoice, $filters['from'], $filters['to'], [...$filters, 'priced' => true]);
        $lists    = $this->sales->priceLists($workspace, $lines->pluck('price_list_id')->unique()->values()->all())->keyBy('id');
        $rows     = [];
        $invoices = [];

        foreach ($lines as $line) {
            $list = $lists->get($line->price_list_id);

            if (! $list) {
                continue;
            }

            $rule = $list->rules->firstWhere('id', $line->price_list_rule_id);
            $key  = "{$list->id}:".($rule?->id ?? 'gone');

            $rows[$key] ??= [
                'list'      => $list->name,
                'rule'      => $rule ? $this->describe($rule, $list->currency) : 'A rule since removed',
                'lines'     => 0,
                'invoices'  => 0,
                'quantity'  => '0',
                'net_sales' => '0.00',
                'link'      => "/sales/price-lists/{$list->id}",
                'order'     => $rule?->position ?? PHP_INT_MAX,
            ];
            $rows[$key]['lines']++;
            $rows[$key]['quantity']  = bcadd($rows[$key]['quantity'], Decimal::of($line->quantity), 3);
            $rows[$key]['net_sales'] = $this->add($rows[$key]['net_sales'], $this->figures->lineNet($line));

            $invoices[$key][$line->sales_document_id] = true;
            $invoices['all'][$line->sales_document_id] = true;
            $rows[$key]['invoices'] = count($invoices[$key]);
        }

        uasort($rows, fn (array $a, array $b) => strcmp($a['list'], $b['list']) ?: $a['order'] <=> $b['order']);

        $rows = array_values(array_map(fn (array $row) => array_diff_key($row, ['order' => true]), $rows));

        return new ReportTable(
            title: 'Price List Usage',
            period: $this->between($filters),
            columns: [
                $this->column('list', 'Price List'),
                $this->column('rule', 'Rule'),
                $this->column('lines', 'Lines', 'number'),
                $this->column('invoices', 'Invoices', 'number'),
                $this->column('quantity', 'Quantity', 'number'),
                $this->column('net_sales', 'Net Sales', 'money', $base),
            ],
            rows: $rows,
            totals: ['list' => 'Total', 'lines' => array_sum(array_column($rows, 'lines')), 'invoices' => count($invoices['all'] ?? []), ...$this->totals($rows, ['net_sales'])],
            notes: ["Issued invoice lines a price list priced, as each line kept it, in {$base} before tax. Credit notes are not taken off."],
        );
    }

    /** A rule as the price list's form puts it: "Office Chair: USD 120.00", "Furniture (category), from 5: 10% off its price". */
    private function describe(PriceListRule $rule, string $currency): string
    {
        $what = match ($rule->target) {
            'item'     => $rule->item->name ?? 'An item',
            'category' => ($rule->category->name ?? 'A category').' (category)',
            'brand'    => ($rule->brand->name ?? 'A brand').' (brand)',
            default    => 'All items',
        };
        $from  = bccomp(Decimal::of($rule->min_quantity), '1', 3) > 0 ? ', from '.$this->plain($rule->min_quantity) : '';
        $price = match ($rule->method) {
            'fixed'    => "{$currency} ".Decimal::round(Decimal::of($rule->value)),
            'discount' => $this->plain($rule->value).'% off its price',
            default    => $this->plain($rule->value).'% over its cost',
        };

        return "{$what}{$from}: {$price}";
    }

    /** "5.000" as "5", "12.5000" as "12.5". */
    private function plain(mixed $value): string
    {
        $value = Decimal::of($value);

        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
