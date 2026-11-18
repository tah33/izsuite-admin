<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Sales Summary (Doc 07 section 5): issued invoices by their dates less issued
 * credit notes by theirs, in the base currency, grouped by day, week, month,
 * customer, item, location or currency. Net Sales leaves tax out; quotations,
 * proformas, drafts, cancelled and void invoices never count. The Tax column
 * shows while taxes apply (section 16).
 *
 * For one item or item category the figures come from those lines alone.
 * A customer's or an item's row opens their profile, with its related
 * documents or transactions (Doc 04 section 20); a period's, location's or
 * currency's opens the documents behind it.
 *
 * The full Sales app adds groupings (Doc 16 section 16.26 - Phase H2a): by
 * item category and brand - each line's item as it is now - salesperson,
 * sales team and source, the app that made each document. Its "Sales by"
 * reports are this one grouped one way, under their own names
 * (ReportCatalog::GROUPED).
 */
class SalesSummaryReport extends Report
{
    public const GROUPS = [
        'day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'customer' => 'Customer', 'item' => 'Item', 'location' => 'Location', 'currency' => 'Currency',
        'category' => 'Item Category', 'brand' => 'Brand', 'salesperson' => 'Salesperson', 'team' => 'Sales Team', 'source' => 'Source',
    ];

    /** The full Sales app's groupings: Sales Summary takes them only while it is on (ReportService). */
    public const FULL_GROUPS = ['category', 'brand', 'salesperson', 'team', 'source'];

    private const PERIODS = ['day', 'week', 'month'];

    /** The groupings that take each line apart: what an item is, not its document. */
    private const LINE_GROUPS = ['item', 'category', 'brand'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        // Another report's grouping - a supplier, a payee - is by month, as Purchase Summary's is.
        $group  = array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'month';
        $lines  = in_array($group, self::LINE_GROUPS, true) || ($filters['item'] ?? null) || ($filters['category'] ?? null);
        $rows   = $lines ? $this->byLine($workspace, $filters, $group) : $this->byDocument($workspace, $filters, $group);
        $rows   = in_array($group, self::PERIODS, true) ? $rows : $this->ranked($rows);
        $base   = $workspace->base_currency;
        $taxed  = $this->taxesShown($workspace);
        $money  = ['subtotal', 'discounts', 'credit_notes', 'net_sales', 'tax', 'total'];
        $narrow = $filters['item']->name ?? (isset($filters['category']) ? "{$filters['category']->name} items" : null);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Sales Summary',
            period: $this->between($filters).($narrow ? " · {$narrow}" : ''),
            columns: array_values(array_filter([
                $this->column('group', self::GROUPS[$group]),
                $this->column('invoices', 'Invoices', 'number'),
                $this->column('subtotal', 'Subtotal', 'money', $base),
                $this->column('discounts', 'Discounts', 'money', $base),
                $this->column('credit_notes', 'Credit Notes', 'money', $base),
                $this->column('net_sales', 'Net Sales', 'money', $base),
                $taxed ? $this->column('tax', 'Tax', 'money', $base) : null,
                $this->column('total', 'Grand Total', 'money', $base),
            ])),
            rows: array_values($rows),
            totals: ['group' => 'Total', 'invoices' => array_sum(array_column($rows, 'invoices')), ...$this->totals($rows, $money)],
            notes: ["Amounts are in {$base}, at the rate each document kept. Credit notes count on their own dates; Net Sales leaves tax out."],
        );
    }

    /** Each document's totals into its group: invoices add, credit notes take off. */
    private function byDocument(Workspace $workspace, array $filters, string $group): array
    {
        $rows = $this->periodRows($filters, $group);

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $type) {
            foreach ($this->sales->issued($workspace, $type, $filters['from'], $filters['to'], $filters) as $document) {
                [$key, $label, $link] = $this->groupOf($this->documentFacts($document), $group, $filters);

                $rows[$key] ??= $this->row($label, $link);
                $rows[$key]   = $this->tally($rows[$key], $type, [
                    'subtotal'  => $this->figures->base($document->subtotal, $document->exchange_rate),
                    'discounts' => $this->figures->base($document->discount_total, $document->exchange_rate),
                    'net'       => Decimal::of($document->base_net_total),
                    'tax'       => Decimal::of($document->base_tax_total),
                ], 1);
            }
        }

        return $rows;
    }

    /**
     * Each line into its group, with its share of the document's discount:
     * grouped by item, or the sales of one item or category grouped as asked.
     * An invoice counts once in each group its lines fall into.
     */
    private function byLine(Workspace $workspace, array $filters, string $group): array
    {
        $rows = $this->periodRows($filters, $group);
        $seen = [];

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $type) {
            foreach ($this->sales->issuedLines($workspace, $type, $filters['from'], $filters['to'], $filters) as $line) {
                [$key, $label, $link] = $group === 'item'
                    ? [$line->item_id ? "item:{$line->item_id}" : 'line:'.mb_strtolower($line->name), $line->name, $line->item_id ? "/items/{$line->item_id}" : null]
                    : $this->groupOf($this->lineFacts($line), $group, $filters);
                $subtotal = $this->figures->base($line->amount, $line->exchange_rate);
                $net      = $this->figures->lineNet($line);
                $tax      = $this->figures->base($line->tax_amount, $line->exchange_rate);
                $kept     = $line->price_tax_mode === 'inclusive' ? $this->add($net, $tax) : $net;
                $first    = $type === DocumentType::Invoice && ! isset($seen[$key][$line->sales_document_id]);

                $seen[$key][$line->sales_document_id] = true;
                $rows[$key] ??= $this->row($label, $link);
                $rows[$key]   = $this->tally($rows[$key], $type, ['subtotal' => $subtotal, 'discounts' => $this->subtract($subtotal, $kept), 'net' => $net, 'tax' => $tax], $first ? 1 : 0);
            }
        }

        return $rows;
    }

    /** Every period of the range when grouped by one - a quiet one still shows - each opening the documents behind it. */
    private function periodRows(array $filters, string $group): array
    {
        if (! in_array($group, self::PERIODS, true)) {
            return [];
        }

        $rows = [];

        foreach ($this->period->buckets($filters['from'], $filters['to'], $group, $filters['week_start'] ?? null) as $key) {
            [$start, $end] = $this->period->bounds($key, $group, $filters['from'], $filters['to']);
            $rows[$key]    = $this->row($this->period->label($key, $group, $filters['date_format'] ?? null), $this->documentsLink($start, $end, $filters));
        }

        return $rows;
    }

    /** Where a document falls: its day, customer, location and currency - and who sold it and the app that made it. */
    private function documentFacts(SalesDocument $document): array
    {
        return [
            'date'           => $document->document_date,
            'contact_id'     => $document->contact_id,
            'customer'       => $this->figures->customerName($document),
            'location_id'    => $document->location_id,
            'location'       => $document->location?->name,
            'currency'       => $document->currency,
            'salesperson_id' => $document->salesperson_id,
            'salesperson'    => $document->salesperson?->name,
            'team_id'        => $document->sales_team_id,
            'team'           => $document->salesTeam?->name,
            'source_id'      => $document->source_app_id,
            'source'         => $document->sourceApp?->name,
        ];
    }

    /** Where a line's document falls - from issuedLines()'s columns - and its item's category and brand. */
    private function lineFacts(SalesDocumentLine $line): array
    {
        return [
            'date'           => $line->document_date,
            'contact_id'     => $line->contact_id,
            'customer'       => json_decode((string) $line->customer_snapshot, true)['name'] ?? $line->contact_name ?? 'Unknown customer',
            'location_id'    => $line->location_id,
            'location'       => $line->location_name,
            'currency'       => $line->currency,
            'category_id'    => $line->category_id,
            'category'       => $line->category_name,
            'brand_id'       => $line->brand_id,
            'brand'          => $line->brand_name,
            'salesperson_id' => $line->salesperson_id,
            'salesperson'    => $line->salesperson_id ? trim($line->salesperson_first_name.' '.$line->salesperson_last_name) : null,
            'team_id'        => $line->sales_team_id,
            'team'           => $line->team_name,
            'source_id'      => $line->source_app_id,
            'source'         => $line->source_name,
        ];
    }

    /** @return array{0: string, 1: string, 2: ?string} the group's key, what the report calls it, and where its row leads */
    private function groupOf(array $facts, string $group, array $filters): array
    {
        if (in_array($group, self::PERIODS, true)) {
            $key = $this->period->bucket($facts['date'], $group, $filters['week_start'] ?? null);

            return [$key, $this->period->label($key, $group, $filters['date_format'] ?? null), null];
        }

        return match ($group) {
            'customer'    => ["contact:{$facts['contact_id']}", $facts['customer'], $facts['contact_id'] ? "/contacts/{$facts['contact_id']}" : null],
            'location'    => [
                "location:{$facts['location_id']}",
                $facts['location'] ?? 'No location',
                $facts['location_id'] ? $this->documentsLink($filters['from'], $filters['to'], $filters, ['location_id' => $facts['location_id']]) : null,
            ],
            // The full Sales app's (Doc 16 Phase H2a): each without one in a row of its own.
            'category'    => ["category:{$facts['category_id']}", $facts['category'] ?? 'No category', null],
            'brand'       => ["brand:{$facts['brand_id']}", $facts['brand'] ?? 'No brand', null],
            'salesperson' => ["salesperson:{$facts['salesperson_id']}", $facts['salesperson'] ?? 'No salesperson', null],
            'team'        => ["team:{$facts['team_id']}", $facts['team'] ?? 'No team', null],
            'source'      => ["source:{$facts['source_id']}", $facts['source'] ?? 'Not recorded', null],
            default       => [$facts['currency'], $facts['currency'], $this->documentsLink($filters['from'], $filters['to'], $filters, ['currency' => $facts['currency']])],
        };
    }

    private function row(string $label, ?string $link = null): array
    {
        return ['group' => $label, 'invoices' => 0, 'subtotal' => '0.00', 'discounts' => '0.00', 'credit_notes' => '0.00', 'net_sales' => '0.00', 'tax' => '0.00', 'total' => '0.00', 'link' => $link];
    }

    /** An invoice's figures add to the row; a credit note's net and tax come off it. */
    private function tally(array $row, DocumentType $type, array $figures, int $count): array
    {
        if ($type === DocumentType::Invoice) {
            $row['invoices'] += $count;
            $row['subtotal']  = $this->add($row['subtotal'], $figures['subtotal']);
            $row['discounts'] = $this->add($row['discounts'], $figures['discounts']);
            $row['net_sales'] = $this->add($row['net_sales'], $figures['net']);
            $row['tax']       = $this->add($row['tax'], $figures['tax']);
        } else {
            $row['credit_notes'] = $this->subtract($row['credit_notes'], $figures['net']);
            $row['net_sales']    = $this->subtract($row['net_sales'], $figures['net']);
            $row['tax']          = $this->subtract($row['tax'], $figures['tax']);
        }

        $row['total'] = $this->add($row['net_sales'], $row['tax']);

        return $row;
    }

    /** Biggest net sales first. */
    private function ranked(array $rows): array
    {
        uasort($rows, fn (array $a, array $b) => bccomp($b['net_sales'], $a['net_sales'], 2) ?: strcmp($a['group'], $b['group']));

        return $rows;
    }
}
