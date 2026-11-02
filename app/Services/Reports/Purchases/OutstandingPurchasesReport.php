<?php

namespace App\Services\Reports\Purchases;

use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Support\Decimal;

/**
 * Outstanding Purchases (Doc 07 section 12): recorded purchases with something
 * still owed to the supplier on the as-of date - after payments and tax
 * withheld by then - tax included, most overdue first, with the same aging
 * groups as Outstanding Invoices. Each purchase in its own currency; the
 * groups and total in the base one. Tax Withheld shows while withholding
 * applies. It may be narrowed to unpaid or partly paid purchases.
 */
class OutstandingPurchasesReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base  = $workspace->base_currency;
        $asOf  = $filters['as_of'];
        $rows  = $this->rows($workspace, $asOf, $filters);
        $aging = array_fill_keys(OutstandingInvoicesReport::GROUPS, '0.00');

        foreach ($rows as $row) {
            $aging[$row['aging']] = $this->add($aging[$row['aging']], $row['base_outstanding']);
        }

        return new ReportTable(
            title: 'Outstanding Purchases',
            period: 'As of '.$this->day($asOf, $filters),
            columns: array_values(array_filter([
                $this->column('number', 'Purchase'),
                $this->column('supplier', 'Supplier'),
                $this->column('document_date', 'Purchase Date', 'date'),
                $this->column('due_date', 'Due Date', 'date'),
                $this->column('age', 'Age'),
                $this->column('currency', 'Currency'),
                $this->column('total', 'Purchase Total', 'money'),
                $this->column('paid', 'Amount Paid', 'money'),
                $this->withholdingShown($workspace) ? $this->column('withholding', 'Tax Withheld', 'money') : null,
                $this->column('outstanding', 'Outstanding', 'money'),
                $this->column('base_outstanding', "Outstanding ({$base})", 'money', $base),
                $this->column('payment_status', 'Payment Status'),
                $this->column('aging', 'Overdue Status'),
                $this->column('location', 'Location'),
            ])),
            rows: $rows,
            totals: ['number' => 'Total', 'base_outstanding' => Decimal::sum(array_column($rows, 'base_outstanding'))],
            summary: array_map(fn (string $group) => ['label' => $group, 'value' => $aging[$group], 'currency' => $base], OutstandingInvoicesReport::GROUPS),
            notes: ['Overdue: past the due date with something left to pay. Amounts include tax.'],
        );
    }

    /** The purchases still owing on $asOf, most overdue first. */
    public function rows(Workspace $workspace, string $asOf, array $filters): array
    {
        $rows = [];

        foreach ($this->purchases->purchasesAsOf($workspace, $asOf, $filters) as $purchase) {
            $owed = $this->figures->purchaseOwedAsOf($purchase);

            if (Decimal::isZero($owed)) {
                continue;
            }

            $days    = $this->figures->daysOverdue($purchase->due_date, $asOf);
            $settled = Decimal::sum([Decimal::of($purchase->paid_by), Decimal::of($purchase->withheld_by)]);
            $status  = Decimal::isZero($settled) ? 'unpaid' : 'partially_paid';

            if (($filters['payment_status'] ?? $status) !== $status) {
                continue;
            }

            $rows[] = [
                'id'               => $purchase->id,
                'number'           => $purchase->number,
                'supplier'         => $this->figures->supplierName($purchase),
                'document_date'    => $purchase->document_date,
                'due_date'         => $purchase->due_date,
                'days_overdue'     => $days,
                'age'              => $days > 0 ? ($days === 1 ? '1 day overdue' : "{$days} days overdue") : 'Not due',
                'aging'            => $this->figures->agingGroup($days),
                'currency'         => $purchase->currency,
                'total'            => (string) $purchase->grand_total,
                'paid'             => Decimal::round(Decimal::of($purchase->paid_by)),
                'withholding'      => Decimal::round(Decimal::of($purchase->withheld_by)),
                'outstanding'      => $owed,
                'base_outstanding' => $this->figures->base($owed, $purchase->exchange_rate),
                'payment_status'   => OutstandingInvoicesReport::PAYMENT_STATUSES[$status],
                'location'         => $purchase->location?->name,
                'link'             => "/purchases/{$purchase->id}",
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['days_overdue'] <=> $a['days_overdue'] ?: strcmp((string) $a['due_date'], (string) $b['due_date']));

        return $rows;
    }
}
