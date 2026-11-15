<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * The Discount Report (Doc 16 section 16.26 - Phase H2b): each issued invoice
 * of the period that gave a discount - on its lines, on the whole, or both -
 * oldest first. What it came to before them, each kind and their total are in
 * the base currency, at the rate the invoice kept, and as it shows its
 * prices; with the share given, the customer, who sold it, and why a discount
 * went past a role's limit (Phase F2). Each row opens its invoice.
 */
class DiscountReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];

        foreach ($this->sales->discounted($workspace, $filters['from'], $filters['to'], $filters) as $invoice) {
            $lines    = Decimal::of($invoice->line_discounts);
            $before   = $this->figures->base(bcadd(Decimal::of($invoice->subtotal), $lines, 2), $invoice->exchange_rate);
            $onLines  = $this->figures->base($lines, $invoice->exchange_rate);
            $onWhole  = $this->figures->base($invoice->discount_total, $invoice->exchange_rate);
            $discount = $this->add($onLines, $onWhole);

            $rows[] = [
                'document_date'    => $invoice->document_date,
                'number'           => $invoice->number,
                'customer'         => $this->figures->customerName($invoice),
                'salesperson'      => $invoice->salesperson?->name,
                'before'           => $before,
                'line_discounts'   => $onLines,
                'invoice_discount' => $onWhole,
                'discount'         => $discount,
                'percent'          => $this->percent($discount, $before),
                'reason'           => $invoice->discount_reason,
                'link'             => "/sales/invoices/{$invoice->id}",
            ];
        }

        $totals = $this->totals($rows, ['before', 'line_discounts', 'invoice_discount', 'discount']);

        return new ReportTable(
            title: 'Discount Report',
            period: $this->between($filters),
            columns: [
                $this->column('document_date', 'Date', 'date'),
                $this->column('number', 'Invoice'),
                $this->column('customer', 'Customer'),
                $this->column('salesperson', 'Salesperson'),
                $this->column('before', 'Before Discounts', 'money', $base),
                $this->column('line_discounts', 'Line Discounts', 'money', $base),
                $this->column('invoice_discount', 'Invoice Discount', 'money', $base),
                $this->column('discount', 'Total Discount', 'money', $base),
                $this->column('percent', 'Discount %', 'percent'),
                $this->column('reason', 'Reason Past the Limit'),
            ],
            rows: $rows,
            totals: ['document_date' => 'Total', ...$totals, 'percent' => $this->percent($totals['discount'], $totals['before'])],
            notes: ["Issued invoices with a discount, in {$base} at the rate each kept, and as each shows its prices: with tax, where they include it. A reason shows where someone allowed to went past a role's limit."],
        );
    }
}
