<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Sales Trend (Doc 07 section 6): net sales over time - by day, week or
 * month as the range is long - with what credit notes took off, tax left out.
 * Where sales went, not profit or a forecast. Also the Dashboard's chart.
 */
class SalesTrendReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = $this->rows($workspace, $filters);

        return new ReportTable(
            title: 'Sales Trend',
            period: $this->between($filters),
            columns: [
                $this->column('period', 'Period'),
                $this->column('invoices', 'Invoices', 'number'),
                $this->column('sales', 'Sales', 'money', $base),
                $this->column('credit_notes', 'Credit Note Adjustments', 'money', $base),
                $this->column('net_sales', 'Net Sales', 'money', $base),
            ],
            rows: $rows,
            totals: ['period' => 'Total', 'invoices' => array_sum(array_column($rows, 'invoices')), ...$this->totals($rows, ['sales', 'credit_notes', 'net_sales'])],
            notes: ["Amounts are in {$base} and leave tax out."],
            chart: ['label' => 'period', 'value' => 'net_sales'],
        );
    }

    /** One row a period from $filters['from'] to $filters['to'], quiet ones too. */
    public function rows(Workspace $workspace, array $filters): array
    {
        $grain = $this->period->grain($filters['from'], $filters['to']);
        $rows  = [];

        foreach ($this->period->buckets($filters['from'], $filters['to'], $grain) as $key) {
            $rows[$key] = ['period' => $this->period->label($key, $grain), 'invoices' => 0, 'sales' => '0.00', 'credit_notes' => '0.00', 'net_sales' => '0.00'];
        }

        foreach ($this->sales->issued($workspace, DocumentType::Invoice, $filters['from'], $filters['to'], $filters) as $invoice) {
            $key = $this->period->bucket($invoice->document_date, $grain);

            $rows[$key]['invoices']++;
            $rows[$key]['sales']     = $this->add($rows[$key]['sales'], Decimal::of($invoice->base_net_total));
            $rows[$key]['net_sales'] = $this->add($rows[$key]['net_sales'], Decimal::of($invoice->base_net_total));
        }

        foreach ($this->sales->issued($workspace, DocumentType::CreditNote, $filters['from'], $filters['to'], $filters) as $note) {
            $key = $this->period->bucket($note->document_date, $grain);

            $rows[$key]['credit_notes'] = $this->subtract($rows[$key]['credit_notes'], Decimal::of($note->base_net_total));
            $rows[$key]['net_sales']    = $this->subtract($rows[$key]['net_sales'], Decimal::of($note->base_net_total));
        }

        return array_values($rows);
    }
}
