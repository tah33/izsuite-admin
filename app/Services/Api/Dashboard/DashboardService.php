<?php

namespace App\Services\Api\Dashboard;

use App\Enums\DocumentType;
use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\PaymentAccount;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use App\Repositories\User\AccountTransactionRepository;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Services\Reports\Sales\SalesTrendReport;
use App\Services\Reports\SalesFigures;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;
use Closure;
use Illuminate\Support\Collection;

/**
 * The Billing Starter Dashboard's figures (Doc 02, Doc 08 section 21, Doc 09
 * section 18, Doc 10 section 17) for a range - This Month unless told - and
 * a location: Sales Lite's cards and the invoices most overdue while Sales
 * is on, the Purchases card while Purchases is on, the Expenses card while
 * Expenses is on, the Transaction Overview with a series for each of them,
 * the newest transactions of all three, and each payment account's balance.
 * Totals are in the base currency; what is owed, and balances, as they
 * stood at the end of the range - or today, if it ends later.
 *
 * Each part only for a role that may see it (Doc 12 section 21): an app's
 * cards and series with its records' View, each kind of transaction with its
 * own, the balances with Payment Accounts' - null otherwise, as for an app
 * that is off.
 */
class DashboardService
{
    private const OUTSTANDING_SHOWN = 5;

    private const RECENT_SHOWN = 8;

    /** What a role needs to see an app's part of the Dashboard. */
    private const VIEWS = ['sales' => 'sales.invoices.view', 'purchases' => 'purchases.purchases.view', 'expenses' => 'expenses.expenses.view'];

    public function __construct(
        protected ReportPeriod $reportPeriod,
        protected SalesReportRepository $salesReportRepository,
        protected PurchaseReportRepository $purchaseReportRepository,
        protected ExpenseReportRepository $expenseReportRepository,
        protected SalesFigures $salesFigures,
        protected SalesTrendReport $salesTrendReport,
        protected OutstandingInvoicesReport $outstandingInvoicesReport,
        protected SalesDocumentRules $salesDocumentRules,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected AccountTransactionRepository $accountTransactionRepository,
        protected WorkspaceAccess $access,
    ) {}

    /** $input is validated (see DashboardRequest). */
    public function summary(Workspace $workspace, array $input): array
    {
        [$from, $to] = $this->reportPeriod->range($workspace, $input['range'] ?? null, $input['date_from'] ?? null, $input['date_to'] ?? null);

        $today   = $this->reportPeriod->today($workspace);
        $asOf    = min($to, $today);
        $filters = ['from' => $from, 'to' => $to, 'location_id' => $input['location_id'] ?? null, 'week_start' => $workspace->weekStartDay(), 'date_format' => $workspace->date_format];
        $apps    = WorkspaceApps::for($workspace);
        $sources = array_filter(['sales' => $apps->row('sales')?->app?->name, 'purchases' => $apps->row('purchases')?->app?->name, 'expenses' => $apps->row('expenses')?->app?->name]);
        $on      = fn (string $family) => $apps->has($family) && isset($sources[$family]) && $this->access->can(self::VIEWS[$family]);

        return [
            'from'      => $from,
            'to'        => $to,
            'as_of'     => $asOf,
            'sales'     => $on('sales') ? $this->sales($workspace, $filters, $asOf) : null,
            'purchases' => $on('purchases') ? $this->purchases($workspace, $filters) : null,
            'expenses'  => $on('expenses') ? $this->expenses($workspace, $filters) : null,
            'chart'     => $on('sales') || $on('purchases') || $on('expenses')
                ? $this->chart($workspace, $filters, $on('sales'), $on('purchases'), $on('expenses'))
                : null,
            'recent'    => $this->recent($workspace, $filters['location_id'], $today, array_filter($sources, fn (string $family) => $apps->has($family), ARRAY_FILTER_USE_KEY)),
            'accounts'  => $this->access->can('payment_accounts.view') ? $this->accounts($workspace, $asOf) : null,
        ];
    }

    private function sales(Workspace $workspace, array $filters, string $asOf): array
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
            'outstanding_rows'  => array_slice($outstanding, 0, self::OUTSTANDING_SHOWN),
        ];
    }

    /** The Purchases card (Doc 02 section 3.4): what was bought - after discounts, without tax - and how many purchases. */
    private function purchases(Workspace $workspace, array $filters): array
    {
        $purchases = $this->purchaseReportRepository->recorded($workspace, $filters['from'], $filters['to'], $filters);

        return [
            'amount' => Decimal::sum($purchases->map(fn (Purchase $purchase) => Decimal::of($purchase->base_net_total))),
            'count'  => $purchases->count(),
        ];
    }

    /**
     * The Expenses card (Doc 02 section 3.5): what was spent - with the tax
     * that cannot be claimed back, without the tax that can - and how many
     * expenses were recorded.
     */
    private function expenses(Workspace $workspace, array $filters): array
    {
        $expenses = $this->expenseReportRepository->recorded($workspace, $filters['from'], $filters['to'], $filters);

        return [
            'amount' => Decimal::sum($expenses->map(fn (Expense $expense) => $this->salesFigures->baseExpenseAmount($expense))),
            'count'  => $expenses->count(),
        ];
    }

    /**
     * The Transaction Overview (Doc 02 section 6): a row a period - by day,
     * week or month as the range is long - with net sales, purchases and
     * expenses, each without the tax shown apart, and null for an app that
     * is off.
     */
    private function chart(Workspace $workspace, array $filters, bool $sales, bool $purchases, bool $expenses): array
    {
        $grain = $this->reportPeriod->grain($filters['from'], $filters['to']);
        $rows  = [];

        foreach ($this->reportPeriod->buckets($filters['from'], $filters['to'], $grain, $filters['week_start']) as $key) {
            $rows[$key] = [
                'label'     => $this->reportPeriod->label($key, $grain, $filters['date_format']),
                'sales'     => $sales ? '0.00' : null,
                'purchases' => $purchases ? '0.00' : null,
                'expenses'  => $expenses ? '0.00' : null,
            ];
        }

        // Sales Trend has a row for each period too, in the same order.
        if ($sales) {
            foreach (array_map(null, array_keys($rows), $this->salesTrendReport->rows($workspace, $filters)) as [$key, $row]) {
                $rows[$key]['sales'] = $row['net_sales'];
            }
        }

        if ($purchases) {
            foreach ($this->purchaseReportRepository->recorded($workspace, $filters['from'], $filters['to'], $filters) as $purchase) {
                $key = $this->reportPeriod->bucket($purchase->document_date, $grain, $filters['week_start']);

                $rows[$key]['purchases'] = Decimal::round(bcadd($rows[$key]['purchases'], Decimal::of($purchase->base_net_total), 10));
            }
        }

        if ($expenses) {
            foreach ($this->expenseReportRepository->recorded($workspace, $filters['from'], $filters['to'], $filters) as $expense) {
                $key = $this->reportPeriod->bucket($expense->document_date, $grain, $filters['week_start']);

                $rows[$key]['expenses'] = bcadd($rows[$key]['expenses'], $this->salesFigures->baseExpenseAmount($expense), 2);
            }
        }

        return array_values($rows);
    }

    /**
     * The newest documents and payments of the apps in $sources together,
     * newest first, with the app they came from (Doc 02 section 9) - of the
     * kinds the role may view (Doc 12), so each opens.
     *
     * @param  array<string, string>  $sources  each family on, and the app it is on as: "Sales Lite"
     */
    private function recent(Workspace $workspace, ?int $locationId, string $today, array $sources): array
    {
        return collect([
            ...(isset($sources['sales']) ? $this->recentSales($workspace, $locationId, $today, $sources['sales']) : []),
            ...(isset($sources['purchases']) ? $this->recentPurchases($workspace, $locationId, $sources['purchases']) : []),
            ...(isset($sources['expenses']) ? $this->recentExpenses($workspace, $locationId, $sources['expenses']) : []),
        ])
            ->sortByDesc(fn (array $row) => [$row['date'], $row['created']])
            ->take(self::RECENT_SHOWN)
            ->values()
            ->all();
    }

    private function recentSales(Workspace $workspace, ?int $locationId, string $today, string $source): array
    {
        $types = $this->access->viewable(array_values(DocumentType::KINDS));

        $documents = ($types ? $this->salesReportRepository->latestDocuments($workspace, self::RECENT_SHOWN, $locationId, $types) : collect())->map(fn (SalesDocument $document) => [
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

        $payments = $this->ifAllowed('sales.payments.view', fn () => $this->salesReportRepository->latestMoney($workspace, self::RECENT_SHOWN, $locationId))->map(fn (SalesPayment $payment) => [
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

        return $documents->concat($payments)->all();
    }

    private function recentPurchases(Workspace $workspace, ?int $locationId, string $source): array
    {
        $purchases = $this->ifAllowed('purchases.purchases.view', fn () => $this->purchaseReportRepository->latestPurchases($workspace, self::RECENT_SHOWN, $locationId))->map(fn (Purchase $purchase) => [
            'date'     => $purchase->document_date,
            'type'     => 'Purchase',
            'number'   => $purchase->number,
            'contact'  => $purchase->supplier_snapshot['name'] ?? $purchase->contact?->name,
            'amount'   => (string) $purchase->grand_total,
            'currency' => $purchase->currency,
            'status'   => $purchase->status->value,
            'source'   => $source,
            'link'     => "/purchases/{$purchase->id}",
            'created'  => $purchase->created_at?->getTimestamp() ?? 0,
        ]);

        $payments = $this->ifAllowed('purchases.payments.view', fn () => $this->purchaseReportRepository->latestPayments($workspace, self::RECENT_SHOWN, $locationId))->map(fn (SupplierPayment $payment) => [
            'date'     => $payment->payment_date,
            'type'     => 'Supplier Payment',
            'number'   => $payment->number,
            'contact'  => $payment->contact?->name,
            'amount'   => (string) $payment->amount,
            'currency' => $payment->account?->currency,
            'status'   => $payment->status,
            'source'   => $source,
            'link'     => "/purchases/payments/{$payment->id}",
            'created'  => $payment->created_at?->getTimestamp() ?? 0,
        ]);

        return $purchases->concat($payments)->all();
    }

    private function recentExpenses(Workspace $workspace, ?int $locationId, string $source): array
    {
        $expenses = $this->ifAllowed('expenses.expenses.view', fn () => $this->expenseReportRepository->latestExpenses($workspace, self::RECENT_SHOWN, $locationId))->map(fn (Expense $expense) => [
            'date'     => $expense->document_date,
            'type'     => 'Expense',
            'number'   => $expense->number,
            'contact'  => $expense->payeeName(),
            'amount'   => (string) $expense->grand_total,
            'currency' => $expense->currency,
            'status'   => $expense->status->value,
            'source'   => $source,
            'link'     => "/expenses/{$expense->id}",
            'created'  => $expense->created_at?->getTimestamp() ?? 0,
        ]);

        $payments = $this->ifAllowed('expenses.payments.view', fn () => $this->expenseReportRepository->latestPayments($workspace, self::RECENT_SHOWN, $locationId))->map(fn (ExpensePayment $payment) => [
            'date'     => $payment->payment_date,
            'type'     => 'Expense Payment',
            'number'   => $payment->number,
            'contact'  => $payment->expense?->payeeName(),
            'amount'   => (string) $payment->amount,
            'currency' => $payment->account?->currency,
            'status'   => $payment->status,
            'source'   => $source,
            'link'     => "/expenses/payments/{$payment->id}",
            'created'  => $payment->created_at?->getTimestamp() ?? 0,
        ]);

        return $expenses->concat($payments)->all();
    }

    /** What $query finds, while the role holds $permission (Doc 12) - nothing otherwise. */
    private function ifAllowed(string $permission, Closure $query): Collection
    {
        return $this->access->can($permission) ? $query() : collect();
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
