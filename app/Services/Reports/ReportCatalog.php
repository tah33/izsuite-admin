<?php

namespace App\Services\Reports;

use App\Models\User\Workspace;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Reports\Accounts\AccountTransactionsReport;
use App\Services\Reports\Purchases\OutstandingPurchasesReport;
use App\Services\Reports\Purchases\PaymentsMadeReport;
use App\Services\Reports\Purchases\PurchaseSummaryReport;
use App\Services\Reports\Sales\ContactStatementReport;
use App\Services\Reports\Sales\CreditNotesReport;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Services\Reports\Sales\PaymentsReceivedReport;
use App\Services\Reports\Sales\SalesSummaryReport;
use App\Services\Reports\Sales\SalesTrendReport;
use App\Services\Reports\Sales\TaxDetailsReport;
use App\Services\Reports\Sales\TaxSummaryReport;
use App\Services\Reports\Sales\TopCustomersReport;
use App\Services\Reports\Sales\TopItemsReport;
use App\Services\User\Apps\WorkspaceApps;

/**
 * The Reports page's reports (Doc 07 section 2, Doc 08 section 22, Doc 09
 * section 19), by category, with the filters each takes (section 3). An
 * app's reports show while the business has its records - on, or turned off
 * since (Doc 06 section 19); Contact Statement while Sales or Purchases has;
 * Tax Summary and Tax Details while taxes are on or tax history exists (Doc
 * 07 section 16); Payment Account Transactions always. Expenses adds its
 * own with its app.
 */
class ReportCatalog
{
    public const CATEGORIES = [
        'sales'     => 'Sales & Receivables',
        'purchases' => 'Purchases & Payables',
        'taxes'     => 'Taxes',
        'contacts'  => 'Contacts',
        'accounts'  => 'Payment Accounts',
    ];

    /** Keys a report was known by before, and the report each is now. */
    private const ALIASES = ['customer-statement' => 'contact-statement'];

    /** Each report: its name, category, class, filters, what it shows, and what it needs - Sales or Purchases records, either (contacts), tax too, or nothing. */
    private const REPORTS = [
        'sales-summary'        => ['Sales Summary', 'sales', SalesSummaryReport::class, ['period', 'group_by', 'location', 'currency', 'item', 'category'], 'Invoices less credit notes for a period - by day, week, month, customer, item, location or currency.', 'sales'],
        'sales-trend'          => ['Sales Trend', 'sales', SalesTrendReport::class, ['period', 'location'], 'Net sales over time, by day, week or month.', 'sales'],
        'top-items'            => ['Top-Selling Items', 'sales', TopItemsReport::class, ['period', 'location', 'category'], 'What sold most, less what was credited.', 'sales'],
        'top-customers'        => ['Top Customers', 'sales', TopCustomersReport::class, ['period', 'location'], 'Who bought most, what they paid, and what they still owe.', 'sales'],
        'outstanding-invoices' => ['Outstanding Invoices', 'sales', OutstandingInvoicesReport::class, ['as_of', 'location', 'contact', 'currency', 'payment_status'], 'Invoices still to be paid on a day, and how overdue they are.', 'sales'],
        'payments-received'    => ['Payments Received', 'sales', PaymentsReceivedReport::class, ['period', 'location', 'contact', 'currency', 'payment_account'], 'Customer payments by date, less refunds, with tax withheld apart.', 'sales'],
        'credit-notes'         => ['Credit Note Details', 'sales', CreditNotesReport::class, ['period', 'location', 'contact'], 'Credit notes issued, and what became of their credit.', 'sales'],
        'purchase-summary'     => ['Purchase Summary', 'purchases', PurchaseSummaryReport::class, ['period', 'group_by', 'location', 'currency', 'item', 'category'], 'Recorded purchases for a period - by day, week, month, supplier, item, category, location or currency.', 'purchases'],
        'outstanding-purchases' => ['Outstanding Purchases', 'purchases', OutstandingPurchasesReport::class, ['as_of', 'location', 'contact', 'currency', 'payment_status'], 'Purchases still to be paid on a day, and how overdue they are.', 'purchases'],
        'payments-made'        => ['Payments Made', 'purchases', PaymentsMadeReport::class, ['period', 'location', 'contact', 'currency', 'payment_account'], 'Supplier payments by date, with tax withheld apart.', 'purchases'],
        'tax-summary'          => ['Tax Summary', 'taxes', TaxSummaryReport::class, ['period', 'location', 'tax_treatment'], 'Tax charged on invoices, reversed by credit notes and paid on purchases, by rate and treatment.', 'taxes'],
        'tax-details'          => ['Tax Details', 'taxes', TaxDetailsReport::class, ['period', 'location', 'tax_treatment', 'tax'], "Each invoice's, credit note's and purchase's tax, in its own currency and the base currency.", 'taxes'],
        'contact-statement'    => ['Contact Statement', 'contacts', ContactStatementReport::class, ['period', 'contact', 'side', 'currency'], "A customer's or supplier's documents and payments, with a running balance.", 'contacts'],
        'account-transactions' => ['Payment Account Transactions', 'accounts', AccountTransactionsReport::class, ['period', 'payment_account'], "An account's money in and out, with its running balance.", null],
    ];

    public function __construct(
        protected SalesReportRepository $salesReportRepository,
        protected PurchaseReportRepository $purchaseReportRepository,
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
        $has       = [
            'sales'     => $sales,
            'purchases' => $purchases,
            'contacts'  => $sales || $purchases,
            'taxes'     => ($sales || $purchases) && ($workspace->usesTaxes()
                || ($sales && $this->salesReportRepository->hasTaxHistory($workspace))
                || ($purchases && $this->purchaseReportRepository->hasTaxHistory($workspace))),
        ];
        $entries = [];

        foreach (self::REPORTS as $key => [$name, $category, , $filters, $description, $needs]) {
            if (! $needs || $has[$needs]) {
                $entries[] = ['key' => $key, 'name' => $name, 'category' => self::CATEGORIES[$category], 'description' => $description, 'filters' => $filters, 'link' => null];
            }
        }

        return $entries;
    }

    /** @return array{key: string, name: string, class: class-string<Report>, filters: list<string>} a report the business can open, by its key or one it had before - 404 otherwise */
    public function find(Workspace $workspace, string $key): array
    {
        $key   = self::ALIASES[$key] ?? $key;
        $entry = collect($this->available($workspace))->firstWhere('key', $key);

        abort_unless($entry && ! $entry['link'], 404, 'There is no such report, or its app is not on.');

        return [...$entry, 'class' => self::REPORTS[$key][2]];
    }
}
