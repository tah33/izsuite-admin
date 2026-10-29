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
 * proformas, drafts, cancelled and void invoices never count. A customer's or
 * an item's row opens their profile, with its related documents or
 * transactions (Doc 04 section 20).
 */
class SalesSummaryReport extends Report
{
    public const GROUPS = ['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'customer' => 'Customer', 'item' => 'Item', 'location' => 'Location', 'currency' => 'Currency'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $group = $filters['group_by'] ?? 'month';
        $rows  = $group === 'item' ? $this->byItem($workspace, $filters) : $this->byDocument($workspace, $filters, $group);
        $base  = $workspace->base_currency;
        $money = ['subtotal', 'discounts', 'credit_notes', 'net_sales', 'tax', 'total'];

        return new ReportTable(
            title: 'Sales Summary',
            period: $this->between($filters),
            columns: [
                $this->column('group', self::GROUPS[$group]),
                $this->column('invoices', 'Invoices', 'number'),
                $this->column('subtotal', 'Subtotal', 'money', $base),
                $this->column('discounts', 'Discounts', 'money', $base),
                $this->column('credit_notes', 'Credit Notes', 'money', $base),
                $this->column('net_sales', 'Net Sales', 'money', $base),
                $this->column('tax', 'Tax', 'money', $base),
                $this->column('total', 'Grand Total', 'money', $base),
            ],
            rows: array_values($rows),
            totals: ['group' => 'Total', 'invoices' => array_sum(array_column($rows, 'invoices')), ...$this->totals($rows, $money)],
            notes: ["Amounts are in {$base}, at the rate each document kept. Credit notes count on their own dates; Net Sales leaves tax out."],
        );
    }

    /** Each document's totals into its group: invoices add, credit notes take off. */
    private function byDocument(Workspace $workspace, array $filters, string $group): array
    {
        $rows = [];

        if (in_array($group, ['day', 'week', 'month'], true)) {
            foreach ($this->period->buckets($filters['from'], $filters['to'], $group) as $key) {
                $rows[$key] = $this->row($this->period->label($key, $group));
            }
        }

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $type) {
            foreach ($this->sales->issued($workspace, $type, $filters['from'], $filters['to'], $filters) as $document) {
                [$key, $label, $link] = $this->groupOf($document, $group);

                $rows[$key] ??= $this->row($label, $link);
                $rows[$key]   = $this->tally($rows[$key], $type, [
                    'subtotal'  => $this->figures->base($document->subtotal, $document->exchange_rate),
                    'discounts' => $this->figures->base($document->discount_total, $document->exchange_rate),
                    'net'       => Decimal::of($document->base_net_total),
                    'tax'       => Decimal::of($document->base_tax_total),
                ], 1);
            }
        }

        return in_array($group, ['day', 'week', 'month'], true) ? $rows : $this->ranked($rows);
    }

    /** Each line into its item's row, with its share of the document's discount. */
    private function byItem(Workspace $workspace, array $filters): array
    {
        $rows = [];
        $seen = [];

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $type) {
            foreach ($this->sales->issuedLines($workspace, $type, $filters['from'], $filters['to'], $filters) as $line) {
                $key      = $line->item_id ? "item:{$line->item_id}" : 'line:'.mb_strtolower($line->name);
                $subtotal = $this->figures->base($line->amount, $line->exchange_rate);
                $net      = $this->figures->lineNet($line);
                $tax      = $this->figures->base($line->tax_amount, $line->exchange_rate);
                $kept     = $line->price_tax_mode === 'inclusive' ? $this->add($net, $tax) : $net;
                $first    = $type === DocumentType::Invoice && ! isset($seen[$key][$line->sales_document_id]);

                $seen[$key][$line->sales_document_id] = true;
                $rows[$key] ??= $this->row($line->name, $line->item_id ? "/items/{$line->item_id}" : null);
                $rows[$key]   = $this->tally($rows[$key], $type, ['subtotal' => $subtotal, 'discounts' => $this->subtract($subtotal, $kept), 'net' => $net, 'tax' => $tax], $first ? 1 : 0);
            }
        }

        return $this->ranked($rows);
    }

    /** @return array{0: string, 1: string, 2: ?string} the group's key, what the report calls it, and where its row leads */
    private function groupOf(SalesDocument $document, string $group): array
    {
        if (in_array($group, ['day', 'week', 'month'], true)) {
            $key = $this->period->bucket($document->document_date, $group);

            return [$key, $this->period->label($key, $group), null];
        }

        return match ($group) {
            'customer' => ["contact:{$document->contact_id}", $this->figures->customerName($document), $document->contact_id ? "/contacts/{$document->contact_id}" : null],
            'location' => ["location:{$document->location_id}", $document->location?->name ?? 'No location', null],
            default    => [$document->currency, $document->currency, null],
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
