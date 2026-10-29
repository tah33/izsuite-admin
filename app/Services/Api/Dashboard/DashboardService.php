<?php

namespace App\Services\Api\Dashboard;

use App\Enums\DocumentType;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Repositories\User\AccountTransactionRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Services\Reports\Sales\SalesTrendReport;
use App\Services\Reports\SalesFigures;
use App\Services\Sales\SalesDocumentRules;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;

/**
 * The Billing Starter Dashboard's figures (Doc 02, Doc 08 section 21) for a
 * range - This Month unless told - and a location: Sales Lite's cards, its
 * chart, the invoices most overdue and the newest transactions, while Sales
 * is on; and each payment account's balance. Totals are in the base currency;
 * what is owed, and balances, as they stood at the end of the range - or
 * today, if it ends later.
 */
class DashboardService
{
    private const OUTSTANDING_SHOWN = 5;

    private const RECENT_SHOWN = 8;

    public function __construct(
        protected ReportPeriod $reportPeriod,
        protected SalesReportRepository $salesReportRepository,
        protected SalesFigures $salesFigures,
        protected SalesTrendReport $salesTrendReport,
        protected OutstandingInvoicesReport $outstandingInvoicesReport,
        protected SalesDocumentRules $salesDocumentRules,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected AccountTransactionRepository $accountTransactionRepository,
    ) {}

    /** $input is validated (see DashboardRequest). */
    public function summary(Workspace $workspace, array $input): array
    {
        [$from, $to] = $this->reportPeriod->range($workspace, $input['range'] ?? null, $input['date_from'] ?? null, $input['date_to'] ?? null);

        $today   = $this->reportPeriod->today($workspace);
        $asOf    = min($to, $today);
        $filters = ['from' => $from, 'to' => $to, 'location_id' => $input['location_id'] ?? null];
        $apps    = WorkspaceApps::for($workspace);

        return [
            'from'     => $from,
            'to'       => $to,
            'as_of'    => $asOf,
            'sales'    => $apps->has('sales') ? $this->sales($workspace, $filters, $asOf, $today, $apps->row('sales')->app->name) : null,
            'accounts' => $this->accounts($workspace, $asOf),
        ];
    }

    /** $source is the app Sales is on as: "Sales Lite" or "Sales". */
    private function sales(Workspace $workspace, array $filters, string $asOf, string $today, string $source): array
    {
        $invoices    = $this->salesReportRepository->issued($workspace, DocumentType::Invoice, $filters['from'], $filters['to'], $filters);
        $notes       = $this->salesReportRepository->issued($workspace, DocumentType::CreditNote, $filters['from'], $filters['to'], $filters);
        $money       = $this->salesReportRepository->moneyMoved($workspace, $filters['from'], $filters['to'], $filters);
        $outstanding = $this->outstandingInvoicesReport->rows($workspace, $asOf, $filters);
        $overdue     = array_filter($outstanding, fn (array $row) => $row['days_overdue'] > 0);
        $net         = fn ($documents) => Decimal::sum($documents->map(fn (SalesDocument $document) => Decimal::of($document->base_net_total)));
        $moved       = fn (string $kind) => Decimal::sum($money->where('kind', $kind)->map(fn (SalesPayment $payment) => Decimal::of($payment->base_amount)));

        return [
            'net_sales'         => bcsub($net($invoices), $net($notes), 2),
            'invoices'          => $invoices->count(),
            'received'          => bcsub($moved(SalesPayment::PAYMENT), $moved(SalesPayment::REFUND), 2),
            'outstanding'       => Decimal::sum(array_column($outstanding, 'base_outstanding')),
            'outstanding_count' => count($outstanding),
            'overdue'           => Decimal::sum(array_column($overdue, 'base_outstanding')),
            'overdue_count'     => count($overdue),
            'chart'             => array_map(fn (array $row) => ['label' => $row['period'], 'value' => $row['net_sales']], $this->salesTrendReport->rows($workspace, $filters)),
            'outstanding_rows'  => array_slice($outstanding, 0, self::OUTSTANDING_SHOWN),
            'recent'            => $this->recent($workspace, $filters['location_id'], $today, $source),
        ];
    }

    /** The newest documents and payments together, newest first, with the app they came from (Doc 02 section 9). */
    private function recent(Workspace $workspace, ?int $locationId, string $today, string $source): array
    {
        $documents = $this->salesReportRepository->latestDocuments($workspace, self::RECENT_SHOWN, $locationId)->map(fn (SalesDocument $document) => [
            'date'     => $document->document_date,
            'type'     => $document->type->label(),
            'number'   => $document->number,
            'contact'  => $this->salesFigures->customerName($document),
            'amount'   => (string) $document->grand_total,
            'currency' => $document->currency,
            'status'   => $this->salesDocumentRules->status($document, $today),
            'source'   => $source,
            'link'     => "/sales/{$document->type->kind()}/{$document->id}",
            'created'  => $document->created_at?->getTimestamp() ?? 0,
        ]);

        $payments = $this->salesReportRepository->latestMoney($workspace, self::RECENT_SHOWN, $locationId)->map(fn (SalesPayment $payment) => [
            'date'     => $payment->payment_date,
            'type'     => $payment->kind === SalesPayment::REFUND ? 'Customer Refund' : 'Customer Payment',
            'number'   => $payment->number,
            'contact'  => $payment->contact?->name,
            'amount'   => (string) $payment->amount,
            'currency' => $payment->account?->currency,
            'status'   => $payment->status,
            'source'   => $source,
            'link'     => "/sales/payments/{$payment->id}",
            'created'  => $payment->created_at?->getTimestamp() ?? 0,
        ]);

        return $documents->concat($payments)
            ->sortByDesc(fn (array $row) => [$row['date'], $row['created']])
            ->take(self::RECENT_SHOWN)
            ->values()
            ->all();
    }

    /** Each account's recorded balance on $asOf: its opening balance, once dated by then, and the money moved by then. */
    private function accounts(Workspace $workspace, string $asOf): array
    {
        $net = $this->accountTransactionRepository->netByAccountUntil($workspace, $asOf);

        return $this->paymentAccountRepository->getFor($workspace)->map(fn (PaymentAccount $account) => [
            'id'       => $account->id,
            'name'     => $account->name,
            'type'     => $account->type,
            'currency' => $account->currency,
            'active'   => (bool) $account->is_active,
            'balance'  => Decimal::round(bcadd(
                ! $account->opening_balance_date || $account->opening_balance_date <= $asOf ? (string) $account->opening_balance : '0',
                Decimal::of($net[$account->id] ?? null),
                10,
            )),
        ])->values()->all();
    }
}
