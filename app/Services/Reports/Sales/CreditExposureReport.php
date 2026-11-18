<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Api\Sales\CustomerCredit;
use App\Services\Reports\Report;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportTable;
use App\Services\Reports\SalesFigures;

/**
 * Customer Credit Exposure (Doc 16 sections 16.21 and 16.26 - Phase H3c):
 * each customer who owes, holds credit or has orders that count - or who has
 * a credit limit - the most exposed first, as their Credit Control card
 * works it out (CustomerCredit, Phase G1): their limit, what their invoices
 * still owe and of it what is overdue, what their open orders have not
 * invoiced yet while Settings > Sales counts them, less the credit they hold
 * unapplied - their exposure - and what is left of their limit. In the base
 * currency, as of today. Each row opens the customer.
 */
class CreditExposureReport extends Report
{
    private const MONEY = ['outstanding', 'overdue', 'orders', 'unapplied', 'exposure'];

    public function __construct(
        SalesReportRepository $sales,
        SalesFigures $figures,
        ReportPeriod $period,
        PurchaseReportRepository $purchases,
        ExpenseReportRepository $expenses,
        protected CustomerCredit $credit,
    ) {
        parent::__construct($sales, $figures, $period, $purchases, $expenses);
    }

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base   = $workspace->base_currency;
        $counts = false;
        $rows   = [];

        foreach ($this->credit->exposures($workspace) as $id => $customer) {
            $counts = $customer['counts_orders'];
            $rows[] = [
                'customer'    => $customer['name'],
                'limit'       => $customer['credit_limit'],
                'outstanding' => $customer['outstanding'],
                'overdue'     => $customer['overdue'],
                'orders'      => $customer['orders'],
                'unapplied'   => $customer['unapplied'],
                'exposure'    => $customer['exposure'],
                'available'   => $customer['available'],
                'standing'    => $customer['over'] ? 'Over the limit' : ($customer['credit_sales'] ? null : 'No credit sales'),
                'link'        => "/contacts/{$id}",
            ];
        }

        usort($rows, fn (array $a, array $b) => bccomp($b['exposure'], $a['exposure'], 2) ?: strcmp($a['customer'], $b['customer']));

        return new ReportTable(
            title: 'Customer Credit Exposure',
            period: 'As of '.$this->day($filters['as_of'], $filters),
            columns: array_values(array_filter([
                $this->column('customer', 'Customer'),
                $this->column('limit', 'Credit Limit', 'money', $base),
                $this->column('outstanding', 'Outstanding', 'money', $base),
                $this->column('overdue', 'Overdue', 'money', $base),
                $counts ? $this->column('orders', 'Orders Not Invoiced', 'money', $base) : null,
                $this->column('unapplied', 'Unapplied Credit', 'money', $base),
                $this->column('exposure', 'Exposure', 'money', $base),
                $this->column('available', 'Available', 'money', $base),
                $this->column('standing', 'Standing'),
            ])),
            rows: $rows,
            totals: ['customer' => 'Total', ...$this->totals($rows, self::MONEY)],
            notes: ["Each customer's exposure as their Credit Control card works it out, in {$base}: what their invoices still owe".($counts ? ', and what their confirmed and held orders have not invoiced yet' : '').', less the advances and credit notes they hold unapplied. Available is their credit limit less their exposure; no limit, no figure.'],
        );
    }
}
