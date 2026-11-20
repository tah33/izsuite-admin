<?php

namespace App\Services\Reports\Pos;

use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PosReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Api\Pos\PosSessionTotals;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportTable;
use App\Services\Reports\SalesFigures;
use App\Services\Shared\BusinessCalendar;

/**
 * Session Summary (Doc 18 section 18.36 - Phase H3): each session opened in
 * the period, by the business's days, oldest first - where, whose, where it
 * stands, its opening float, its sales, the cash it should hold, what was
 * counted and the difference, each in its own currency. A counted session's
 * figures are those its closing kept - the latest, when it was reopened -
 * and one still open's as they stand now.
 */
class PosSessionsReport extends PosReport
{
    private const STATUSES = [PosSession::OPEN => 'Open', PosSession::SUSPENDED => 'Suspended', PosSession::CLOSING => 'Waiting for approval', PosSession::CLOSED => 'Closed'];

    public function __construct(
        SalesReportRepository $sales,
        SalesFigures $figures,
        ReportPeriod $period,
        PurchaseReportRepository $purchases,
        ExpenseReportRepository $expenses,
        PosReportRepository $pos,
        BusinessCalendar $calendar,
        protected PosSessionTotals $sessionTotals,
    ) {
        parent::__construct($sales, $figures, $period, $purchases, $expenses, $pos, $calendar);
    }

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        [$from, $to] = $this->instants($workspace, $filters);
        $rows        = [];

        foreach ($this->pos->sessions($workspace, $from, $to, $filters) as $session) {
            $counted = $session->closing_id !== null && in_array($session->status, PosSession::COUNTED, true);
            $figures = $counted ? (json_decode((string) $session->closing_totals, true) ?: []) : $this->sessionTotals->of($session);

            $rows[] = [
                'number'        => $session->number,
                'register'      => $session->register_name ? "{$session->register_name} ({$session->register_code})" : 'Removed',
                'cashier'       => $this->person($session->cashier_first_name, $session->cashier_last_name),
                'opened'        => $this->dayOf($workspace, $session->opened_at),
                'status'        => self::STATUSES[$session->status] ?? ucfirst((string) $session->status),
                'currency'      => $session->currency,
                'opening_float' => (string) $session->opening_float,
                'sales'         => (int) ($figures['sales_count'] ?? 0),
                'sales_total'   => $this->cents($figures['sales_total'] ?? null),
                'expected'      => $this->cents($counted ? $session->expected_cash : ($figures['expected_cash'] ?? null)),
                'counted'       => $counted ? $this->cents($session->counted_cash) : null,
                'variance'      => $counted ? $this->cents($session->variance) : null,
            ];
        }

        $currency = $this->oneCurrency($rows);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Session Summary',
            period: $this->scope($filters),
            columns: [
                $this->column('number', 'Session'),
                $this->column('register', 'Register'),
                $this->column('cashier', 'Cashier'),
                $this->column('opened', 'Opened', 'date'),
                $this->column('status', 'Status'),
                $this->column('currency', 'Currency'),
                $this->column('opening_float', 'Opening Float', 'money'),
                $this->column('sales', 'Sales', 'number'),
                $this->column('sales_total', 'Sales Total', 'money'),
                $this->column('expected', 'Expected Cash', 'money'),
                $this->column('counted', 'Counted Cash', 'money'),
                $this->column('variance', 'Variance', 'money'),
            ],
            rows: $rows,
            totals: $currency ? ['number' => 'Total', 'currency' => $currency, 'sales' => array_sum(array_column($rows, 'sales')), ...$this->totals($rows, ['sales_total', 'variance'])] : [],
            notes: [
                'The sessions opened in the period, each in its register\'s currency. A counted session shows what its closing kept - the latest, if it was reopened - and one not counted yet its figures now: Expected Cash is the opening float, cash sales and cash put in, less cash paid back and taken out.',
                $currency ? 'The totals add the sales and the variances.' : 'The sessions are in more than one currency, so there are no totals.',
            ],
        );
    }
}
