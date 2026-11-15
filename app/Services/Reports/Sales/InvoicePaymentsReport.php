<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * The Invoice and Payment Report (Doc 16 section 16.26 - Phase H3c): each
 * invoice issued in the period, oldest first, and how it stands today - what
 * its payments, the advances applied to it, its credit (its credit notes'
 * credit kept on it, and other credit notes applied to it) and the tax
 * withheld settled, and what it still owes - in the base currency, at its
 * rate. Each row opens the invoice.
 */
class InvoicePaymentsReport extends Report
{
    private const MONEY = ['total', 'payments', 'advances', 'credit', 'withholding', 'outstanding'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base     = $workspace->base_currency;
        $withheld = $this->withholdingShown($workspace);
        $invoices = $this->sales->invoicesAsOf($workspace, $filters['as_of'], [...$filters, 'issued_from' => $filters['from'], 'issued_to' => $filters['to']])
            ->sortBy([['document_date', 'asc'], ['id', 'asc']]);
        $rows     = [];

        foreach ($invoices as $invoice) {
            $rate     = $invoice->exchange_rate;
            $advances = Decimal::of($invoice->advanced_by);
            $credit   = Decimal::sum([
                bcsub(bcsub(Decimal::of($invoice->credited_by), Decimal::of($invoice->refunded_by), 2), Decimal::of($invoice->given_by), 2),
                bcsub(Decimal::of($invoice->allocated_by), $advances, 2),
            ]);
            $owed    = $this->figures->owedAsOf($invoice);
            $settled = Decimal::sum([Decimal::of($invoice->paid_by), Decimal::of($invoice->withheld_by), $advances, $credit]);

            $rows[] = [
                'document_date' => $invoice->document_date,
                'number'        => $invoice->number,
                'customer'      => $this->figures->customerName($invoice),
                'total'         => Decimal::round(Decimal::of($invoice->base_grand_total)),
                'payments'      => $this->figures->base($invoice->paid_by, $rate),
                'advances'      => $this->figures->base($advances, $rate),
                'credit'        => $this->figures->base($credit, $rate),
                'withholding'   => $this->figures->base($invoice->withheld_by, $rate),
                'outstanding'   => $this->figures->base($owed, $rate),
                'status'        => Decimal::isZero($owed) ? 'Paid' : (Decimal::isZero($settled) ? 'Unpaid' : 'Partially Paid'),
                'link'          => "/sales/invoices/{$invoice->id}",
            ];
        }

        return new ReportTable(
            title: 'Invoice and Payment Report',
            period: $this->between($filters),
            columns: array_values(array_filter([
                $this->column('document_date', 'Date', 'date'),
                $this->column('number', 'Invoice'),
                $this->column('customer', 'Customer'),
                $this->column('total', 'Total', 'money', $base),
                $this->column('payments', 'Payments', 'money', $base),
                $this->column('advances', 'Advances', 'money', $base),
                $this->column('credit', 'Credit', 'money', $base),
                $withheld ? $this->column('withholding', 'Tax Withheld', 'money', $base) : null,
                $this->column('outstanding', 'Outstanding', 'money', $base),
                $this->column('status', 'Status'),
            ])),
            rows: $rows,
            totals: ['document_date' => 'Total', ...$this->totals($rows, self::MONEY)],
            notes: ["Invoices issued in the period, as each stands today, in {$base} at the rate each kept. Advances are the customer's money paid ahead and applied; credit is from credit notes."],
        );
    }
}
