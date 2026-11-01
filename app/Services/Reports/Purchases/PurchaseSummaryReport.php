<?php

namespace App\Services\Reports\Purchases;

use App\Models\User\Purchase;
use App\Models\User\PurchaseLine;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Purchase Summary (Doc 07 section 11): recorded purchases by their dates, in
 * the base currency, grouped by day, week, month, supplier, item, item
 * category, location or currency. Purchase Amount leaves separately
 * identified tax out; drafts and void purchases never count. The Tax column
 * shows while taxes apply (section 16).
 *
 * For one item or item category - or grouped by them - the figures come from
 * the lines alone, each with its share of the purchase's discount. A
 * supplier's or an item's row opens their profile; a period's, location's or
 * currency's opens the purchases behind it.
 */
class PurchaseSummaryReport extends Report
{
    public const GROUPS = ['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'supplier' => 'Supplier', 'item' => 'Item', 'category' => 'Item Category', 'location' => 'Location', 'currency' => 'Currency'];

    private const PERIODS = ['day', 'week', 'month'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $group  = array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'month';
        $lines  = in_array($group, ['item', 'category'], true) || ($filters['item'] ?? null) || ($filters['category'] ?? null);
        $rows   = $lines ? $this->byLine($workspace, $filters, $group) : $this->byPurchase($workspace, $filters, $group);
        $rows   = in_array($group, self::PERIODS, true) ? $rows : $this->ranked($rows);
        $count  = count(array_unique(array_merge([], ...array_column($rows, 'seen'))));
        $rows   = array_map(fn (array $row) => array_diff_key($row, ['seen' => true]), $rows);
        $base   = $workspace->base_currency;
        $taxed  = $this->taxesShown($workspace);
        $narrow = $filters['item']->name ?? (isset($filters['category']) ? "{$filters['category']->name} items" : null);

        return new ReportTable(
            title: 'Purchase Summary',
            period: $this->between($filters).($narrow ? " · {$narrow}" : ''),
            columns: array_values(array_filter([
                $this->column('group', self::GROUPS[$group]),
                $this->column('purchases', 'Purchases', 'number'),
                $this->column('subtotal', 'Subtotal', 'money', $base),
                $this->column('discounts', 'Discounts', 'money', $base),
                $this->column('amount', 'Purchase Amount', 'money', $base),
                $taxed ? $this->column('tax', 'Tax', 'money', $base) : null,
                $this->column('total', 'Grand Total', 'money', $base),
            ])),
            rows: array_values($rows),
            // A purchase whose lines fall into two groups counts once in the total.
            totals: ['group' => 'Total', 'purchases' => $count, ...$this->totals($rows, ['subtotal', 'discounts', 'amount', 'tax', 'total'])],
            notes: ["Amounts are in {$base}, at the rate each purchase kept. Purchase Amount is after discounts and leaves tax out; drafts and void purchases are left out."],
        );
    }

    /** Each purchase's totals into its group. */
    private function byPurchase(Workspace $workspace, array $filters, string $group): array
    {
        $rows = $this->periodRows($filters, $group);

        foreach ($this->purchases->recorded($workspace, $filters['from'], $filters['to'], $filters) as $purchase) {
            [$key, $label, $link] = $this->groupOf($this->purchaseFacts($purchase), $group, $filters);

            $rows[$key] ??= $this->row($label, $link);
            $rows[$key]   = $this->tally($rows[$key], $purchase->id, [
                'subtotal'  => $this->figures->base($purchase->subtotal, $purchase->exchange_rate),
                'discounts' => $this->figures->base($purchase->discount_total, $purchase->exchange_rate),
                'amount'    => Decimal::round(Decimal::of($purchase->base_net_total)),
                'tax'       => Decimal::round(Decimal::of($purchase->base_tax_total)),
            ]);
        }

        return $rows;
    }

    /**
     * Each line into its group, with its share of the purchase's discount: by
     * item or item category, or the purchases of one item or category grouped
     * as asked. A purchase counts once in each group its lines fall into.
     */
    private function byLine(Workspace $workspace, array $filters, string $group): array
    {
        $rows = $this->periodRows($filters, $group);

        foreach ($this->purchases->recordedLines($workspace, $filters['from'], $filters['to'], $filters) as $line) {
            [$key, $label, $link] = match ($group) {
                'item'     => [$line->item_id ? "item:{$line->item_id}" : 'line:'.mb_strtolower($line->name), $line->name, $line->item_id ? "/items/{$line->item_id}" : null],
                'category' => ["category:{$line->category_id}", $line->category_name ?? 'No category', null],
                default    => $this->groupOf($this->lineFacts($line), $group, $filters),
            };
            $subtotal = $this->figures->base($line->amount, $line->exchange_rate);
            $net      = $this->figures->lineNet($line);
            $tax      = $this->figures->base($line->tax_amount, $line->exchange_rate);
            $kept     = $line->price_tax_mode === 'inclusive' ? $this->add($net, $tax) : $net;

            $rows[$key] ??= $this->row($label, $link);
            $rows[$key]   = $this->tally($rows[$key], $line->purchase_id, ['subtotal' => $subtotal, 'discounts' => $this->subtract($subtotal, $kept), 'amount' => $net, 'tax' => $tax]);
        }

        return $rows;
    }

    /** Every period of the range when grouped by one - a quiet one still shows - each opening the purchases behind it. */
    private function periodRows(array $filters, string $group): array
    {
        if (! in_array($group, self::PERIODS, true)) {
            return [];
        }

        $rows = [];

        foreach ($this->period->buckets($filters['from'], $filters['to'], $group) as $key) {
            [$start, $end] = $this->period->bounds($key, $group, $filters['from'], $filters['to']);
            $rows[$key]    = $this->row($this->period->label($key, $group), $this->purchasesLink($start, $end, $filters));
        }

        return $rows;
    }

    /** Where a purchase falls: its day, supplier, location and currency. */
    private function purchaseFacts(Purchase $purchase): array
    {
        return [
            'date'        => $purchase->document_date,
            'contact_id'  => $purchase->contact_id,
            'supplier'    => $this->figures->supplierName($purchase),
            'location_id' => $purchase->location_id,
            'location'    => $purchase->location?->name,
            'currency'    => $purchase->currency,
        ];
    }

    /** Where a line's purchase falls - from recordedLines()'s columns. */
    private function lineFacts(PurchaseLine $line): array
    {
        return [
            'date'        => $line->document_date,
            'contact_id'  => $line->contact_id,
            'supplier'    => json_decode((string) $line->supplier_snapshot, true)['name'] ?? $line->contact_name ?? 'Unknown supplier',
            'location_id' => $line->location_id,
            'location'    => $line->location_name,
            'currency'    => $line->currency,
        ];
    }

    /** @return array{0: string, 1: string, 2: ?string} the group's key, what the report calls it, and where its row leads */
    private function groupOf(array $facts, string $group, array $filters): array
    {
        if (in_array($group, self::PERIODS, true)) {
            $key = $this->period->bucket($facts['date'], $group);

            return [$key, $this->period->label($key, $group), null];
        }

        return match ($group) {
            'supplier' => ["contact:{$facts['contact_id']}", $facts['supplier'], $facts['contact_id'] ? "/contacts/{$facts['contact_id']}" : null],
            'location' => [
                "location:{$facts['location_id']}",
                $facts['location'] ?? 'No location',
                $facts['location_id'] ? $this->purchasesLink($filters['from'], $filters['to'], $filters, ['location_id' => $facts['location_id']]) : null,
            ],
            default    => [$facts['currency'], $facts['currency'], $this->purchasesLink($filters['from'], $filters['to'], $filters, ['currency' => $facts['currency']])],
        };
    }

    /** The Documents page with the recorded purchases behind a row. */
    private function purchasesLink(string $from, string $to, array $filters, array $narrower = []): string
    {
        return $this->documentsLink($from, $to, $filters, ['status' => 'recorded', 'source' => 'purchases', ...$narrower]);
    }

    private function row(string $label, ?string $link = null): array
    {
        return ['group' => $label, 'purchases' => 0, 'subtotal' => '0.00', 'discounts' => '0.00', 'amount' => '0.00', 'tax' => '0.00', 'total' => '0.00', 'link' => $link, 'seen' => []];
    }

    /** A purchase's figures add to the row - which counts each purchase once. */
    private function tally(array $row, int $purchaseId, array $figures): array
    {
        if (! in_array($purchaseId, $row['seen'], true)) {
            $row['seen'][] = $purchaseId;
            $row['purchases']++;
        }

        $row['subtotal']  = $this->add($row['subtotal'], $figures['subtotal']);
        $row['discounts'] = $this->add($row['discounts'], $figures['discounts']);
        $row['amount']    = $this->add($row['amount'], $figures['amount']);
        $row['tax']       = $this->add($row['tax'], $figures['tax']);
        $row['total']     = $this->add($row['amount'], $row['tax']);

        return $row;
    }

    /** Biggest purchase amount first. */
    private function ranked(array $rows): array
    {
        uasort($rows, fn (array $a, array $b) => bccomp($b['amount'], $a['amount'], 2) ?: strcmp($a['group'], $b['group']));

        return $rows;
    }
}
