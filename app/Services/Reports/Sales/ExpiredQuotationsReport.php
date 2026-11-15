<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * Expired Quotations (Doc 16 section 16.26 - Phase H3a): each draft or sent
 * quotation whose expiry date had passed by the as-of day, the latest to
 * expire first - as it stands now, so one answered or converted since is
 * not here. Its total is in its own currency and the base currency. Each row
 * opens the quotation, which can still be revised or converted (section
 * 16.7).
 */
class ExpiredQuotationsReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $asOf = $filters['as_of'];
        $rows = [];

        foreach ($this->sales->expiredQuotations($workspace, $asOf, $filters) as $quotation) {
            $rows[] = [
                'number'        => $quotation->number ?? 'Draft',
                'document_date' => $quotation->document_date,
                'expiry_date'   => $quotation->expiry_date,
                'days'          => $this->figures->daysOverdue($quotation->expiry_date, $asOf),
                'customer'      => $this->figures->customerName($quotation),
                'salesperson'   => $quotation->salesperson?->name,
                'status'        => Str::headline($quotation->status->value),
                'currency'      => $quotation->currency,
                'total'         => (string) $quotation->grand_total,
                'base_total'    => Decimal::round(Decimal::of($quotation->base_grand_total)),
                'link'          => "/sales/quotations/{$quotation->id}",
            ];
        }

        return new ReportTable(
            title: 'Expired Quotations',
            period: 'As of '.$this->day($asOf, $filters),
            columns: [
                $this->column('number', 'Quotation'),
                $this->column('document_date', 'Date', 'date'),
                $this->column('expiry_date', 'Expiry Date', 'date'),
                $this->column('days', 'Days Expired', 'number'),
                $this->column('customer', 'Customer'),
                $this->column('salesperson', 'Salesperson'),
                $this->column('status', 'Status'),
                // Each row's own, which its Total is in.
                $this->column('currency', 'Currency'),
                $this->column('total', 'Total', 'money'),
                $this->column('base_total', "Total ({$base})", 'money', $base),
            ],
            rows: $rows,
            totals: ['number' => 'Total', 'base_total' => Decimal::sum(array_column($rows, 'base_total'))],
            notes: ["Draft and sent quotations whose expiry date had passed by {$this->day($asOf, $filters)}, as each stands now. Each can still be revised or converted."],
        );
    }
}
