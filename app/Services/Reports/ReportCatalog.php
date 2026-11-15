<?php

namespace App\Services\Reports;

use App\Enums\Edition;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Reports\Accounts\AccountTransactionsReport;
use App\Services\Reports\Expenses\ExpensesByCategoryReport;
use App\Services\Reports\Expenses\ExpenseSummaryReport;
use App\Services\Reports\Purchases\OutstandingPurchasesReport;
use App\Services\Reports\Purchases\PaymentsMadeReport;
use App\Services\Reports\Purchases\PurchaseSummaryReport;
use App\Services\Reports\Sales\ContactStatementReport;
use App\Services\Reports\Sales\CreditNotesReport;
use App\Services\Reports\Sales\DiscountReport;
use App\Services\Reports\Sales\ExpiredQuotationsReport;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Services\Reports\Sales\PaymentsReceivedReport;
use App\Services\Reports\Sales\PriceListUsageReport;
use App\Services\Reports\Sales\QuotationConversionReport;
use App\Services\Reports\Sales\QuotationPerformanceReport;
use App\Services\Reports\Sales\SalesMarginReport;
use App\Services\Reports\Sales\SalesSummaryReport;
use App\Services\Reports\Sales\SalesTrendReport;
use App\Services\Reports\Sales\TaxDetailsReport;
use App\Services\Reports\Sales\TaxSummaryReport;
use App\Services\Reports\Sales\TopCustomersReport;
use App\Services\Reports\Sales\TopItemsReport;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;

/**
 * The Reports page's reports (Doc 07 section 2, Doc 08 section 22, Doc 09
 * section 19, Doc 10 section 18), by category, with the filters each takes
 * (section 3). An app's reports show while the business has its records -
 * on, or turned off since (Doc 06 section 19); Payments Made while
 * Purchases or Expenses has; Contact Statement while any of the three has;
 * Tax Summary and Tax Details while taxes are on or tax history exists (Doc
 * 07 section 16); Payment Account Transactions always; the full Sales app's
 * while it is on (Doc 16 section 16.26). Each only for a role
 * that may see what it shows, values included (PARTS) - the tax reports with
 * Tax Information too, the account's with its balances; another is not
 * found.
 */
class ReportCatalog
{
    public const CATEGORIES = [
        'sales'     => 'Sales & Receivables',
        'purchases' => 'Purchases & Payables',
        'expenses'  => 'Expenses',
        'taxes'     => 'Taxes',
        'contacts'  => 'Contacts',
        'accounts'  => 'Payment Accounts',
    ];

    /** Keys a report was known by before, and the report each is now. */
    private const ALIASES = ['customer-statement' => 'contact-statement'];

    /** The reports that are another one grouped one way, always (Doc 16 Phase H2a): the grouping each is built with. */
    public const GROUPED = [
        'sales-by-customer' => 'customer', 'sales-by-item' => 'item', 'sales-by-category' => 'category', 'sales-by-brand' => 'brand',
        'sales-by-location' => 'location', 'sales-by-salesperson' => 'salesperson', 'sales-by-team' => 'team', 'sales-by-source' => 'source',
    ];

    /**
     * What the full Sales app's reports need (Doc 16 section 16.26): the app
     * on, and sales seen - and for two of them a permission more.
     */
    private const FULL_SALES = ['sales_full' => null, 'sales_costs' => 'items.costs', 'sales_lists' => 'sales.price_lists.view', 'sales_quotes' => 'sales.quotations.view'];

    /**
     * Each report: its name, category, class, filters, what it shows, and what
     * it needs - Sales, Purchases or Expenses records, Purchases' or Expenses'
     * (payables), any app's (contacts), tax too, the full Sales app
     * (FULL_SALES), or nothing.
     */
    private const REPORTS = [
        'sales-summary'        => ['Sales Summary', 'sales', SalesSummaryReport::class, ['period', 'group_by', 'location', 'currency', 'item', 'category'], 'Invoices less credit notes for a period - by day, week, month, customer, item, location or currency.', 'sales'],
        'sales-trend'          => ['Sales Trend', 'sales', SalesTrendReport::class, ['period', 'location'], 'Net sales over time, by day, week or month.', 'sales'],
        'top-items'            => ['Top-Selling Items', 'sales', TopItemsReport::class, ['period', 'location', 'category'], 'What sold most, less what was credited.', 'sales'],
        'top-customers'        => ['Top Customers', 'sales', TopCustomersReport::class, ['period', 'location'], 'Who bought most, what they paid, and what they still owe.', 'sales'],
        'outstanding-invoices' => ['Outstanding Invoices', 'sales', OutstandingInvoicesReport::class, ['as_of', 'location', 'contact', 'currency', 'payment_status'], 'Invoices still to be paid on a day, and how overdue they are.', 'sales'],
        'payments-received'    => ['Payments Received', 'sales', PaymentsReceivedReport::class, ['period', 'location', 'contact', 'currency', 'payment_account'], 'Customer payments by date, less refunds, with tax withheld apart.', 'sales'],
        'credit-notes'         => ['Credit Note Details', 'sales', CreditNotesReport::class, ['period', 'location', 'contact'], 'Credit notes issued, and what became of their credit.', 'sales'],
        // The full Sales app's (Doc 16 Phase H2a): Sales Summary, each grouped one way (GROUPED).
        'sales-by-customer'    => ['Sales by Customer', 'sales', SalesSummaryReport::class, ['period', 'location', 'currency'], 'Invoices less credit notes for a period, before tax, by customer - biggest first.', 'sales_full'],
        'sales-by-item'        => ['Sales by Item', 'sales', SalesSummaryReport::class, ['period', 'location', 'currency'], 'Invoices less credit notes for a period, before tax, by item - biggest first.', 'sales_full'],
        'sales-by-category'    => ['Sales by Category', 'sales', SalesSummaryReport::class, ['period', 'location', 'currency'], "Invoices less credit notes for a period, before tax, by each item's category now.", 'sales_full'],
        'sales-by-brand'       => ['Sales by Brand', 'sales', SalesSummaryReport::class, ['period', 'location', 'currency'], "Invoices less credit notes for a period, before tax, by each item's brand now.", 'sales_full'],
        'sales-by-location'    => ['Sales by Location', 'sales', SalesSummaryReport::class, ['period', 'currency'], 'Invoices less credit notes for a period, before tax, by location.', 'sales_full'],
        'sales-by-salesperson' => ['Sales by Salesperson', 'sales', SalesSummaryReport::class, ['period', 'location', 'currency'], 'Invoices less credit notes for a period, before tax, by the salesperson each names.', 'sales_full'],
        'sales-by-team'        => ['Sales by Sales Team', 'sales', SalesSummaryReport::class, ['period', 'location', 'currency'], 'Invoices less credit notes for a period, before tax, by the sales team each names.', 'sales_full'],
        'sales-by-source'      => ['Sales by Source', 'sales', SalesSummaryReport::class, ['period', 'location', 'currency'], 'Invoices less credit notes for a period, before tax, by the app that made each.', 'sales_full'],
        // Phase H3a's, for those who see quotations.
        'quotation-performance' => ['Quotation Performance', 'sales', QuotationPerformanceReport::class, ['period', 'group_by', 'location', 'currency'], 'Quotations that went out in a period - accepted, converted, rejected, expired or still open - by month, customer, salesperson or team.', 'sales_quotes'],
        'quotation-conversion' => ['Quotation Conversion Rate', 'sales', QuotationConversionReport::class, ['period', 'group_by', 'location', 'currency'], 'The share of quotations won - accepted or converted - by number and by value.', 'sales_quotes'],
        'expired-quotations'   => ['Expired Quotations', 'sales', ExpiredQuotationsReport::class, ['as_of', 'location', 'contact'], 'Draft and sent quotations past their expiry date, to revise, convert or follow up.', 'sales_quotes'],
        // Phase H2b's: the margin for those who see costs, price list usage for those who see price lists.
        'discounts'            => ['Discount Report', 'sales', DiscountReport::class, ['period', 'location', 'contact', 'currency'], 'Invoices that gave a discount, on their lines or the whole, who sold them, and why one went past a limit.', 'sales_full'],
        'price-list-usage'     => ['Price List Usage', 'sales', PriceListUsageReport::class, ['period', 'location', 'currency'], 'The invoice lines each price list and rule priced, and their sales before tax.', 'sales_lists'],
        'sales-margin'         => ['Estimated Sales Margin', 'sales', SalesMarginReport::class, ['period', 'location', 'category'], "Sales by item less each item's cost now - an estimate, not a Profit and Loss Statement.", 'sales_costs'],
        'purchase-summary'     => ['Purchase Summary', 'purchases', PurchaseSummaryReport::class, ['period', 'group_by', 'location', 'currency', 'item', 'category'], 'Recorded purchases for a period - by day, week, month, supplier, item, category, location or currency.', 'purchases'],
        'outstanding-purchases' => ['Outstanding Purchases', 'purchases', OutstandingPurchasesReport::class, ['as_of', 'location', 'contact', 'currency', 'payment_status'], 'Purchases still to be paid on a day, and how overdue they are.', 'purchases'],
        'payments-made'        => ['Payments Made', 'purchases', PaymentsMadeReport::class, ['period', 'location', 'contact', 'currency', 'payment_account'], 'Supplier and expense payments by date, with tax withheld apart.', 'payables'],
        'expense-summary'      => ['Expense Summary', 'expenses', ExpenseSummaryReport::class, ['period', 'location', 'contact', 'currency', 'expense_category'], 'Each recorded expense for a period, with the tax that can be claimed back apart.', 'expenses'],
        'expenses-by-category' => ['Expenses by Category', 'expenses', ExpensesByCategoryReport::class, ['period', 'group_by', 'location', 'currency', 'expense_category'], 'What was spent, paid and still owed - by expense category, payee, location, month or currency.', 'expenses'],
        'tax-summary'          => ['Tax Summary', 'taxes', TaxSummaryReport::class, ['period', 'location', 'tax_treatment'], 'Tax charged on invoices, reversed by credit notes and paid on purchases and expenses, by rate and treatment.', 'taxes'],
        'tax-details'          => ['Tax Details', 'taxes', TaxDetailsReport::class, ['period', 'location', 'tax_treatment', 'tax'], "Each invoice's, credit note's, purchase's and expense's tax, in its own currency and the base currency.", 'taxes'],
        'contact-statement'    => ['Contact Statement', 'contacts', ContactStatementReport::class, ['period', 'contact', 'side', 'currency'], "A customer's or supplier's documents and payments, with a running balance.", 'contacts'],
        'account-transactions' => ['Payment Account Transactions', 'accounts', AccountTransactionsReport::class, ['period', 'payment_account'], "An account's money in and out, with its running balance.", null],
    ];

    /**
     * What seeing a part of a report takes besides Reports' View (Doc 12
     * sections 14 and 21): its records' View and their app's values. A report
     * that draws on several apps needs every part it has records for, so none
     * shows half.
     */
    private const PARTS = [
        'sales'             => ['sales.invoices.view', 'sales.values.view'],
        'purchases'         => ['purchases.purchases.view', 'purchases.values.view'],
        'expenses'          => ['expenses.expenses.view', 'expenses.values.view'],
        'supplier_payments' => ['purchases.payments.view', 'purchases.values.view'],
        'expense_payments'  => ['expenses.payments.view', 'expenses.values.view'],
    ];

    public function __construct(
        protected SalesReportRepository $salesReportRepository,
        protected PurchaseReportRepository $purchaseReportRepository,
        protected ExpenseReportRepository $expenseReportRepository,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * @return list<array{key: string, name: string, category: string, description: string, filters: list<string>, link: ?string}>
     *         the reports the business can open
     */
    public function available(Workspace $workspace): array
    {
        $apps      = WorkspaceApps::for($workspace);
        $sales     = $apps->hasHistory('sales');
        $purchases = $apps->hasHistory('purchases');
        $expenses  = $apps->hasHistory('expenses');
        $has       = [
            'sales'     => $sales,
            'purchases' => $purchases,
            'expenses'  => $expenses,
            'payables'  => $purchases || $expenses,
            'contacts'  => $sales || $purchases || $expenses,
            'taxes'     => ($sales || $purchases || $expenses) && ($workspace->usesTaxes()
                || ($sales && $this->salesReportRepository->hasTaxHistory($workspace))
                || ($purchases && $this->purchaseReportRepository->hasTaxHistory($workspace))
                || ($expenses && $this->expenseReportRepository->hasTaxHistory($workspace))),
            // The full Sales app's own (Doc 16 section 16.26): while it is on.
            ...array_fill_keys(array_keys(self::FULL_SALES), $apps->has('sales', Edition::Full)),
        ];
        $entries = [];

        foreach (self::REPORTS as $key => [$name, $category, , $filters, $description, $needs]) {
            if ((! $needs || $has[$needs]) && $this->opens($needs ?? 'accounts', $sales, $purchases, $expenses)) {
                $entries[] = ['key' => $key, 'name' => $name, 'category' => self::CATEGORIES[$category], 'description' => $description, 'filters' => $filters, 'link' => null];
            }
        }

        return $entries;
    }

    /**
     * The reports app $family brings to the Reports page (Doc 13 section 6):
     * its own, and - shared - those it draws on with the other apps.
     *
     * @return list<array{name: string, shared: bool, edition: ?string}>
     */
    public static function namesFor(string $family): array
    {
        $shared = match ($family) {
            'sales'                 => ['contacts', 'taxes'],
            'purchases', 'expenses' => ['payables', 'contacts', 'taxes'],
            default                 => [],
        };

        $names = [];

        foreach (self::REPORTS as [$name, , , , , $needs]) {
            // The full Sales app's own count as Sales', marked as the full edition's (Doc 16 Phase H2a).
            $edition = array_key_exists($needs ?? '', self::FULL_SALES) ? 'full' : null;
            $needs   = $edition ? 'sales' : $needs;

            if ($needs === $family || in_array($needs, $shared, true)) {
                $names[] = ['name' => $name, 'shared' => $needs !== $family, 'edition' => $edition];
            }
        }

        return $names;
    }

    /** @return array{key: string, name: string, class: class-string<Report>, filters: list<string>} a report the business can open, by its key or one it had before - 404 otherwise */
    public function find(Workspace $workspace, string $key): array
    {
        $key   = self::ALIASES[$key] ?? $key;
        $entry = collect($this->available($workspace))->firstWhere('key', $key);

        abort_unless($entry && ! $entry['link'], 404, 'There is no such report, or its app is not on.');

        return [...$entry, 'class' => self::REPORTS[$key][2]];
    }

    /**
     * Whether the signed-in user may see a statement's $side whole (PARTS): a
     * customer's sales, a supplier's purchases and expenses - those the
     * business has records of.
     */
    public function showsSide(Workspace $workspace, string $side): bool
    {
        $apps = WorkspaceApps::for($workspace);

        return $side === 'customer'
            ? $this->sees(['sales' => true])
            : $this->sees(['purchases' => $apps->hasHistory('purchases'), 'expenses' => $apps->hasHistory('expenses')]);
    }

    /** Whether the signed-in user may open a report that shows $needs, the business having the apps' records it says (PARTS). */
    private function opens(string $needs, bool $sales, bool $purchases, bool $expenses): bool
    {
        return match ($needs) {
            'sales', 'purchases', 'expenses' => $this->sees([$needs => true]),
            'sales_full', 'sales_costs', 'sales_lists', 'sales_quotes' => $this->sees(['sales' => true]) && (self::FULL_SALES[$needs] === null || $this->access->can(self::FULL_SALES[$needs])),
            'payables'   => $this->sees(['supplier_payments' => $purchases, 'expense_payments' => $expenses]),
            'taxes'      => $this->access->can('taxes.view') && $this->sees(['sales' => $sales, 'purchases' => $purchases, 'expenses' => $expenses]),
            // A statement for either side: a customer's, or a supplier's.
            'contacts'   => $this->access->can('contacts.view')
                && ($sales && $this->sees(['sales' => true]) || ($purchases || $expenses) && $this->sees(['purchases' => $purchases, 'expenses' => $expenses])),
            default      => $this->access->can('payment_accounts.view') && $this->access->can('payment_accounts.balances'),
        };
    }

    /** @param  array<string, bool>  $parts  each part, and whether it counts */
    private function sees(array $parts): bool
    {
        return collect($parts)->filter()->keys()->every(fn (string $part) => collect(self::PARTS[$part])->every(fn (string $permission) => $this->access->can($permission)));
    }
}
