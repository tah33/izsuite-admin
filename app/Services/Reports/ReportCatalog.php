<?php

namespace App\Services\Reports;

use App\Models\User\Workspace;
use App\Repositories\User\SalesReportRepository;
use App\Services\Reports\Sales\CreditNotesReport;
use App\Services\Reports\Sales\CustomerStatementReport;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Services\Reports\Sales\PaymentsReceivedReport;
use App\Services\Reports\Sales\SalesSummaryReport;
use App\Services\Reports\Sales\SalesTrendReport;
use App\Services\Reports\Sales\TaxSummaryReport;
use App\Services\Reports\Sales\TopCustomersReport;
use App\Services\Reports\Sales\TopItemsReport;
use App\Services\User\Apps\WorkspaceApps;

/**
 * The Reports page's reports (Doc 07 section 2, Doc 08 section 22), by
 * category, with the filters each takes. An app's reports show while the
 * business has its records - on, or turned off since (Doc 06 section 19);
 * Tax Summary while taxes are on or tax history exists (Doc 07 section 16).
 * Purchases and Expenses add theirs with their apps.
 */
class ReportCatalog
{
    public const CATEGORIES = [
        'sales'    => 'Sales & Receivables',
        'taxes'    => 'Taxes',
        'contacts' => 'Contacts',
        'accounts' => 'Payment Accounts',
    ];

    private const REPORTS = [
        'sales-summary'        => ['Sales Summary', 'sales', SalesSummaryReport::class, ['period', 'group_by', 'location', 'currency'], 'Invoices less credit notes for a period - by day, week, month, customer, item, location or currency.'],
        'sales-trend'          => ['Sales Trend', 'sales', SalesTrendReport::class, ['period', 'location'], 'Net sales over time, by day, week or month.'],
        'top-items'            => ['Top-Selling Items', 'sales', TopItemsReport::class, ['period', 'location'], 'What sold most, less what was credited.'],
        'top-customers'        => ['Top Customers', 'sales', TopCustomersReport::class, ['period', 'location'], 'Who bought most, what they paid, and what they still owe.'],
        'outstanding-invoices' => ['Outstanding Invoices', 'sales', OutstandingInvoicesReport::class, ['as_of', 'location', 'contact', 'currency'], 'Invoices still to be paid on a day, and how overdue they are.'],
        'payments-received'    => ['Payments Received', 'sales', PaymentsReceivedReport::class, ['period', 'location', 'contact', 'currency'], 'Customer payments by date, less refunds, with tax withheld apart.'],
        'credit-notes'         => ['Credit Note Details', 'sales', CreditNotesReport::class, ['period', 'location', 'contact'], 'Credit notes issued, and what became of their credit.'],
        'tax-summary'          => ['Tax Summary', 'taxes', TaxSummaryReport::class, ['period', 'location'], 'Tax charged on invoices and reversed by credit notes, by rate and treatment.'],
        'customer-statement'   => ['Customer Statement', 'contacts', CustomerStatementReport::class, ['period', 'contact', 'currency'], "A customer's invoices, credits and payments, with a running balance."],
    ];

    public function __construct(
        protected SalesReportRepository $salesReportRepository,
    ) {}

    /**
     * @return list<array{key: string, name: string, category: string, description: string, filters: list<string>, link: ?string}>
     *         the reports the business can open, and the page each account's history has
     */
    public function available(Workspace $workspace): array
    {
        $sales   = WorkspaceApps::for($workspace)->hasHistory('sales');
        $taxes   = $sales && ($workspace->usesTaxes() || $this->salesReportRepository->hasTaxHistory($workspace));
        $entries = [];

        foreach (self::REPORTS as $key => [$name, $category, , $filters, $description]) {
            if ($sales && ($key !== 'tax-summary' || $taxes)) {
                $entries[] = ['key' => $key, 'name' => $name, 'category' => self::CATEGORIES[$category], 'description' => $description, 'filters' => $filters, 'link' => null];
            }
        }

        $entries[] = [
            'key'         => 'account-transactions',
            'name'        => 'Payment Account Transactions',
            'category'    => self::CATEGORIES['accounts'],
            'description' => "Each account's money in and out, with its running balance - on the account's own page.",
            'filters'     => [],
            'link'        => '/payment-accounts',
        ];

        return $entries;
    }

    /** @return array{key: string, name: string, class: class-string<Report>, filters: list<string>} a report the business can open - 404 otherwise */
    public function find(Workspace $workspace, string $key): array
    {
        $entry = collect($this->available($workspace))->firstWhere('key', $key);

        abort_unless($entry && ! $entry['link'], 404, 'There is no such report, or its app is not on.');

        return [...$entry, 'class' => self::REPORTS[$key][2]];
    }
}
