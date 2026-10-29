<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Outstanding Invoices (Doc 07 section 9): issued invoices with something
 * still to pay on the as-of date - after payments, tax withheld and credit
 * notes by then - tax included, most overdue first, with the aging groups.
 * Each invoice in its own currency; the groups and total in the base one.
 * Tax Withheld shows while withholding applies. It may be narrowed to
 * unpaid or partly paid invoices.
 */
class OutstandingInvoicesReport extends Report
{
    public const GROUPS = ['Not Due', '1-30 Days Overdue', '31-60 Days Overdue', '61-90 Days Overdue', 'More Than 90 Days Overdue'];

    /** The payment statuses an outstanding invoice can have, by the filter's value. */
    public const PAYMENT_STATUSES = ['unpaid' => 'Unpaid', 'partially_paid' => 'Partially Paid'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base  = $workspace->base_currency;
        $asOf  = $filters['as_of'];
        $rows  = $this->rows($workspace, $asOf, $filters);
        $aging = array_fill_keys(self::GROUPS, '0.00');

        foreach ($rows as $row) {
            $aging[$row['aging']] = $this->add($aging[$row['aging']], $row['base_outstanding']);
        }

        return new ReportTable(
            title: 'Outstanding Invoices',
            period: 'As of '.$this->day($asOf),
            columns: array_values(array_filter([
                $this->column('number', 'Invoice'),
                $this->column('customer', 'Customer'),
                $this->column('document_date', 'Invoice Date', 'date'),
                $this->column('due_date', 'Due Date', 'date'),
                $this->column('age', 'Age'),
                $this->column('currency', 'Currency'),
                $this->column('total', 'Invoice Total', 'money'),
                $this->column('payments', 'Payments', 'money'),
                $this->column('credit_notes', 'Credit Notes', 'money'),
                $this->withholdingShown($workspace) ? $this->column('withholding', 'Tax Withheld', 'money') : null,
                $this->column('outstanding', 'Outstanding', 'money'),
                $this->column('base_outstanding', "Outstanding ({$base})", 'money', $base),
                $this->column('payment_status', 'Payment Status'),
                $this->column('aging', 'Overdue Status'),
                $this->column('location', 'Location'),
            ])),
            rows: $rows,
            totals: ['number' => 'Total', 'base_outstanding' => Decimal::sum(array_column($rows, 'base_outstanding'))],
            summary: array_map(fn (string $group) => ['label' => $group, 'value' => $aging[$group], 'currency' => $base], self::GROUPS),
            notes: ['Overdue: past the due date with something left to pay. Amounts include tax.'],
        );
    }

    /** The invoices still owing on $asOf, most overdue first - the Dashboard's list as well. */
    public function rows(Workspace $workspace, string $asOf, array $filters): array
    {
        $rows = [];

        foreach ($this->sales->invoicesAsOf($workspace, $asOf, $filters) as $invoice) {
            $owed = $this->figures->owedAsOf($invoice);

            if (Decimal::isZero($owed)) {
                continue;
            }

            $days    = $this->figures->daysOverdue($invoice->due_date, $asOf);
            $settled = Decimal::sum([Decimal::of($invoice->paid_by), Decimal::of($invoice->withheld_by), Decimal::of($invoice->credited_by)]);
            $status  = Decimal::isZero($settled) ? 'unpaid' : 'partially_paid';

            if (($filters['payment_status'] ?? $status) !== $status) {
                continue;
            }

            $rows[] = [
                'id'               => $invoice->id,
                'number'           => $invoice->number,
                'customer'         => $this->figures->customerName($invoice),
                'document_date'    => $invoice->document_date,
                'due_date'         => $invoice->due_date,
                'days_overdue'     => $days,
                'age'              => $days > 0 ? ($days === 1 ? '1 day overdue' : "{$days} days overdue") : 'Not due',
                'aging'            => $this->figures->agingGroup($days),
                'currency'         => $invoice->currency,
                'total'            => (string) $invoice->grand_total,
                'payments'         => Decimal::round(Decimal::of($invoice->paid_by)),
                'credit_notes'     => Decimal::round(bcsub(Decimal::of($invoice->credited_by), Decimal::of($invoice->refunded_by), 2)),
                'withholding'      => Decimal::round(Decimal::of($invoice->withheld_by)),
                'outstanding'      => $owed,
                'base_outstanding' => $this->figures->base($owed, $invoice->exchange_rate),
                'payment_status'   => self::PAYMENT_STATUSES[$status],
                'location'         => $invoice->location?->name,
                'link'             => "/sales/invoices/{$invoice->id}",
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['days_overdue'] <=> $a['days_overdue'] ?: strcmp((string) $a['due_date'], (string) $b['due_date']));

        return $rows;
    }
}
