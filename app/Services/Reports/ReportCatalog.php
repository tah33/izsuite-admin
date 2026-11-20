<?php

namespace App\Services\Reports;

use App\Enums\Edition;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Repositories\User\CrmSettingRepository;
use App\Services\Reports\Accounts\AccountTransactionsReport;
use App\Services\Reports\Crm\ActivityCompletionReport;
use App\Services\Reports\Crm\ClosedOpportunitiesReport;
use App\Services\Reports\Crm\ForecastReport;
use App\Services\Reports\Crm\LeadRateReport;
use App\Services\Reports\Crm\LeadResponseReport;
use App\Services\Reports\Crm\LeadsByReport;
use App\Services\Reports\Crm\LostReasonsReport;
use App\Services\Reports\Crm\OpportunitiesByStageReport;
use App\Services\Reports\Crm\OpportunityListReport;
use App\Services\Reports\Crm\OverdueActivitiesReport;
use App\Services\Reports\Crm\PerformanceReport;
use App\Services\Reports\Crm\StageDurationReport;
use App\Services\Reports\Crm\WinRateReport;
use App\Services\Reports\Expenses\ExpensesByCategoryReport;
use App\Services\Reports\Expenses\ExpenseSummaryReport;
use App\Services\Reports\Pos\PosAverageOrderReport;
use App\Services\Reports\Pos\PosCashMovementsReport;
use App\Services\Reports\Pos\PosCashVariancesReport;
use App\Services\Reports\Pos\PosDiscountsReport;
use App\Services\Reports\Pos\PosMarginReport;
use App\Services\Reports\Pos\PosPaymentMethodsReport;
use App\Services\Reports\Pos\PosReturnsReport;
use App\Services\Reports\Pos\PosSalesByReport;
use App\Services\Reports\Pos\PosSessionsReport;
use App\Services\Reports\Pos\PosTaxesReport;
use App\Services\Reports\Pos\PosTopItemsReport;
use App\Services\Reports\Pos\PosVoidsReport;
use App\Services\Reports\Purchases\OutstandingPurchasesReport;
use App\Services\Reports\Purchases\PaymentsMadeReport;
use App\Services\Reports\Purchases\PurchaseSummaryReport;
use App\Services\Reports\Sales\ContactStatementReport;
use App\Services\Reports\Sales\CreditExposureReport;
use App\Services\Reports\Sales\CreditNotesReport;
use App\Services\Reports\Sales\CreditRefundsReport;
use App\Services\Reports\Sales\DeliveryPerformanceReport;
use App\Services\Reports\Sales\DiscountReport;
use App\Services\Reports\Sales\ExpiredQuotationsReport;
use App\Services\Reports\Sales\InvoicePaymentsReport;
use App\Services\Reports\Sales\OrderBacklogReport;
use App\Services\Reports\Sales\OrdersToDeliverReport;
use App\Services\Reports\Sales\OrdersToInvoiceReport;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Services\Reports\Sales\PaymentsReceivedReport;
use App\Services\Reports\Sales\PriceListUsageReport;
use App\Services\Reports\Sales\QuotationConversionReport;
use App\Services\Reports\Sales\QuotationPerformanceReport;
use App\Services\Reports\Sales\ReturnsReport;
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
 * while it is on (Doc 16 section 16.26); CRM's while it is on (Doc 17 section
 * 17.29) - its leads', opportunities' and activities' (Phases H1 to H3) -
 * the lead reports while the business uses leads; POS's while the business
 * has its records (Doc 18 section 18.36). Each only for a
 * role that may see what it shows, values included (PARTS) - the tax reports
 * with Tax Information too, the account's with its balances, CRM's with View
 * CRM Reports and the view of what each counts (CRM), POS's with View POS
 * Reports and the view of what each counts (POS); another is not found.
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
        'crm'       => 'CRM',
        'pos'       => 'Point of Sale',
    ];

    /** Keys a report was known by before, and the report each is now. */
    private const ALIASES = ['customer-statement' => 'contact-statement'];

    /** The reports that are another one grouped one way, always (Doc 16 Phase H2a, Doc 17 Phases H1 and H3): the grouping each is built with. */
    public const GROUPED = [
        'sales-by-customer' => 'customer', 'sales-by-item' => 'item', 'sales-by-category' => 'category', 'sales-by-brand' => 'brand',
        'sales-by-location' => 'location', 'sales-by-salesperson' => 'salesperson', 'sales-by-team' => 'team', 'sales-by-source' => 'source',
        'leads-by-source'   => 'source', 'leads-by-owner' => 'owner', 'leads-by-status' => 'status',
        'sales-team-performance' => 'team', 'salesperson-performance' => 'owner',
        // Doc 18 Phase H1: POS Sales Summary, each grouped one way.
        'pos-sales-by-location' => 'location', 'pos-sales-by-register' => 'register', 'pos-sales-by-cashier' => 'cashier', 'pos-sales-by-salesperson' => 'salesperson',
        'pos-sales-by-item' => 'item', 'pos-sales-by-category' => 'category', 'pos-sales-by-brand' => 'brand', 'pos-sales-by-hour' => 'hour', 'pos-sales-by-day' => 'day',
    ];

    /** How a report with a Group By groups its rows unless told - by month for any not named. */
    public const DEFAULT_GROUPS = ['expenses-by-category' => 'category', 'pipeline-value' => 'stage', 'pos-sales-summary' => 'day', 'pos-average-order' => 'day'];

    /**
     * What CRM's reports need (Doc 17 section 17.29): CRM on, View CRM
     * Reports, and the view of what each counts - leads (while the business
     * uses them), opportunities or activities.
     */
    private const CRM = ['crm_leads' => 'crm.leads.view', 'crm_opportunities' => 'crm.opportunities.view', 'crm_activities' => 'crm.activities.view'];

    /**
     * What POS's reports need (Doc 18 section 18.36): View POS Reports, and
     * the view of what each counts - its orders, for its sales (Phase H1) -
     * and (Phase H2) its returns, taxes besides, or items' costs besides -
     * and (Phase H3) its sessions, or its cash movements.
     */
    private const POS = [
        'pos_sales'   => ['pos.orders.view'],
        'pos_returns' => ['pos.returns.view'],
        'pos_taxes'   => ['pos.orders.view', 'taxes.view'],
        'pos_costs'   => ['pos.orders.view', 'items.costs'],
        'pos_sessions' => ['pos.sessions.view'],
        'pos_cash'    => ['pos.cash.view'],
    ];

    /**
     * What the full Sales app's reports need (Doc 16 section 16.26): the app
     * on, and sales seen - and for two of them a permission more.
     */
    private const FULL_SALES = [
        'sales_full'       => null,
        'sales_costs'      => 'items.costs',
        'sales_lists'      => 'sales.price_lists.view',
        'sales_quotes'     => 'sales.quotations.view',
        'sales_orders'     => 'sales.orders.view',
        // Delivery Performance and the Returns Report show no values, so they need this alone.
        'sales_deliveries' => 'sales.deliveries.view',
        'sales_returns'    => 'sales.returns.view',
    ];

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
        // Phase H3b's: the orders' for those who see orders, Delivery Performance - no values - for those who see deliveries.
        'order-backlog'        => ['Sales Order Backlog', 'sales', OrderBacklogReport::class, ['group_by', 'location', 'currency'], 'Confirmed and held orders - their value, and what is left to deliver and to invoice - by month, customer, salesperson or team.', 'sales_orders'],
        'orders-to-deliver'    => ['Orders to Deliver', 'sales', OrdersToDeliverReport::class, ['location', 'contact'], 'Open orders with goods still to deliver, the earliest expected first, and how late each is.', 'sales_orders'],
        'orders-to-invoice'    => ['Orders to Invoice', 'sales', OrdersToInvoiceReport::class, ['location', 'contact', 'currency'], 'Open orders not invoiced in full, and what is left to invoice.', 'sales_orders'],
        'delivery-performance' => ['Delivery Performance', 'sales', DeliveryPerformanceReport::class, ['period', 'group_by', 'location'], 'Deliveries that arrived in a period, on time or late against the expected day - by month, customer or carrier.', 'sales_deliveries'],
        // Phase H3c's: the money for those who see sales, returns - no values - for those who see returns.
        'invoice-payments'     => ['Invoice and Payment Report', 'sales', InvoicePaymentsReport::class, ['period', 'location', 'contact', 'currency'], 'Invoices of a period and how each was settled - payments, advances, credit and tax withheld - and what is still owed.', 'sales_full'],
        'returns'              => ['Returns Report', 'sales', ReturnsReport::class, ['period', 'group_by', 'location'], 'Returns of a period - open, received or rejected - by month, item, customer, condition or resolution.', 'sales_returns'],
        'credit-refunds'       => ['Credit Notes and Refunds', 'sales', CreditRefundsReport::class, ['period', 'group_by', 'location', 'currency'], 'Credit notes of a period and what became of their credit: applied, refunded or still available.', 'sales_full'],
        // Phase H2b's: the margin for those who see costs, price list usage for those who see price lists.
        'discounts'            => ['Discount Report', 'sales', DiscountReport::class, ['period', 'location', 'contact', 'currency'], 'Invoices that gave a discount, on their lines or the whole, who sold them, and why one went past a limit.', 'sales_full'],
        'price-list-usage'     => ['Price List Usage', 'sales', PriceListUsageReport::class, ['period', 'location', 'currency'], 'The invoice lines each price list and rule priced, and their sales before tax.', 'sales_lists'],
        // Phase H3c's, as of today: where Doc 16 lists it.
        'credit-exposure'      => ['Customer Credit Exposure', 'sales', CreditExposureReport::class, [], "Each customer's exposure against their credit limit - what they owe, less the credit they hold.", 'sales_full'],
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
        // CRM's (Doc 17 Phase H1): its leads, while the business uses them - the leads added in the period, none of them sales.
        'leads-by-source'         => ['Leads by Source', 'crm', LeadsByReport::class, ['period', 'location', 'sales_team', 'owner'], 'The leads added in a period by the source each came with, and what became of them.', 'crm_leads'],
        'leads-by-status'         => ['Leads by Status', 'crm', LeadsByReport::class, ['period', 'location', 'sales_team', 'owner', 'lead_source'], 'The leads added in a period by where each stands now.', 'crm_leads'],
        'leads-by-owner'          => ['Leads by Owner', 'crm', LeadsByReport::class, ['period', 'location', 'sales_team', 'lead_source'], 'The leads added in a period by whom each is given to, and what became of them.', 'crm_leads'],
        'lead-response-time'      => ['Lead Response Time', 'crm', LeadResponseReport::class, ['period', 'group_by', 'location', 'sales_team', 'owner', 'lead_source'], 'How soon the leads added in a period were first contacted, and how many in time.', 'crm_leads'],
        'lead-qualification-rate' => ['Lead Qualification Rate', 'crm', LeadRateReport::class, ['period', 'group_by', 'location', 'sales_team', 'owner', 'lead_source'], 'The share of the leads added in a period that were qualified.', 'crm_leads'],
        'lead-conversion-rate'    => ['Lead Conversion Rate', 'crm', LeadRateReport::class, ['period', 'group_by', 'location', 'sales_team', 'owner', 'lead_source'], 'The share of the leads added in a period that became opportunities.', 'crm_leads'],
        // Phase H2: its opportunities - estimates in the base currency, archived ones left out.
        'opportunities-by-stage'     => ['Opportunities by Stage', 'crm', OpportunitiesByStageReport::class, ['location', 'sales_team', 'owner', 'lead_source', 'pipeline'], 'The open opportunities at each stage now, their value, days at the stage and how many are stale.', 'crm_opportunities'],
        'pipeline-value'             => ['Pipeline Value', 'crm', ForecastReport::class, ['group_by', 'location', 'sales_team', 'owner', 'lead_source', 'pipeline', 'stage'], "The open opportunities' expected value now, by stage, closing month, owner or sales team.", 'crm_opportunities'],
        'weighted-forecast'          => ['Weighted Forecast', 'crm', ForecastReport::class, ['group_by', 'location', 'sales_team', 'owner', 'lead_source', 'pipeline', 'stage'], 'Expected value × probability of the open opportunities, by closing month, stage, owner or sales team.', 'crm_opportunities'],
        'stage-duration'             => ['Stage Duration', 'crm', StageDurationReport::class, ['period', 'location', 'sales_team', 'owner', 'lead_source', 'pipeline'], 'How long opportunities stayed at each stage, and how long those there now have been.', 'crm_opportunities'],
        'opportunities-closing-soon' => ['Opportunities Closing Soon', 'crm', OpportunityListReport::class, ['location', 'sales_team', 'owner', 'lead_source', 'pipeline', 'stage'], 'The open opportunities expected to close within the closing-soon period, the soonest first.', 'crm_opportunities'],
        'won-lost-opportunities'     => ['Won and Lost Opportunities', 'crm', ClosedOpportunitiesReport::class, ['period', 'location', 'sales_team', 'owner', 'lead_source', 'pipeline'], 'Each opportunity won or lost in a period, its value, and why a lost one was lost.', 'crm_opportunities'],
        'win-rate'                   => ['Win Rate', 'crm', WinRateReport::class, ['period', 'group_by', 'location', 'sales_team', 'owner', 'lead_source', 'pipeline'], 'The share of the opportunities closed in a period that were won, by number and by value.', 'crm_opportunities'],
        'lost-reasons'               => ['Lost Reasons', 'crm', LostReasonsReport::class, ['period', 'location', 'sales_team', 'owner', 'lead_source', 'pipeline'], 'Why the opportunities lost in a period were lost, and to whom.', 'crm_opportunities'],
        // Phase H3: people and activity.
        'sales-team-performance'     => ['Sales Team Performance', 'crm', PerformanceReport::class, ['period', 'location'], "Each sales team's leads, opportunities won and lost, value won, open pipeline and activities done.", 'crm_opportunities'],
        'salesperson-performance'    => ['Salesperson Performance', 'crm', PerformanceReport::class, ['period', 'location', 'sales_team'], "Each salesperson's leads, opportunities won and lost, value won, open pipeline and activities done.", 'crm_opportunities'],
        'activity-completion'        => ['Activity Completion', 'crm', ActivityCompletionReport::class, ['period', 'group_by', 'location', 'owner'], 'The activities due in a period: completed, on time, cancelled and still planned.', 'crm_activities'],
        'overdue-activities'         => ['Overdue Activities', 'crm', OverdueActivitiesReport::class, ['location', 'owner'], 'The planned activities past their due time now, the longest overdue first.', 'crm_activities'],
        'opportunities-without-follow-up' => ['Opportunities Without Follow-up', 'crm', OpportunityListReport::class, ['location', 'sales_team', 'owner', 'lead_source', 'pipeline', 'stage'], 'The open opportunities with no activity planned, the longest without activity first.', 'crm_opportunities'],
        // Point of Sale's (Doc 18 Phase H1): its completed sales less their returns, at the registers of the user's locations.
        'pos-sales-summary'        => ['POS Sales Summary', 'pos', PosSalesByReport::class, ['period', 'group_by', 'location', 'register', 'cashier'], 'Completed POS sales less their returns for a period - by day, week, month, location, register, cashier, salesperson, hour, item, category or brand.', 'pos_sales'],
        'pos-sales-by-location'    => ['Sales by Location', 'pos', PosSalesByReport::class, ['period', 'cashier'], 'POS sales less returns for a period, before tax, by location.', 'pos_sales'],
        'pos-sales-by-register'    => ['Sales by Register', 'pos', PosSalesByReport::class, ['period', 'location', 'cashier'], 'POS sales less returns for a period, before tax, by register.', 'pos_sales'],
        'pos-sales-by-cashier'     => ['Sales by Cashier', 'pos', PosSalesByReport::class, ['period', 'location', 'register'], 'POS sales less returns for a period, before tax, by the cashier who made each.', 'pos_sales'],
        'pos-sales-by-salesperson' => ['Sales by Salesperson', 'pos', PosSalesByReport::class, ['period', 'location', 'register'], 'POS sales less returns for a period, before tax, by the salesperson each names.', 'pos_sales'],
        'pos-sales-by-item'        => ['Sales by Item', 'pos', PosSalesByReport::class, ['period', 'location', 'register', 'cashier'], 'The items POS sold for a period, less those returned - quantities and sales before tax.', 'pos_sales'],
        'pos-sales-by-category'    => ['Sales by Category', 'pos', PosSalesByReport::class, ['period', 'location', 'register', 'cashier'], "POS sales less returns for a period, before tax, by each item's category now.", 'pos_sales'],
        'pos-sales-by-brand'       => ['Sales by Brand', 'pos', PosSalesByReport::class, ['period', 'location', 'register', 'cashier'], "POS sales less returns for a period, before tax, by each item's brand now.", 'pos_sales'],
        'pos-sales-by-hour'        => ['Sales by Hour', 'pos', PosSalesByReport::class, ['period', 'location', 'register', 'cashier'], "POS sales less returns for a period, before tax, by the hour of the business's day.", 'pos_sales'],
        'pos-sales-by-day'         => ['Sales by Day', 'pos', PosSalesByReport::class, ['period', 'location', 'register', 'cashier'], 'POS sales less returns for each day of a period, before tax.', 'pos_sales'],
        'pos-payment-methods'      => ['Sales by Payment Method', 'pos', PosPaymentMethodsReport::class, ['period', 'location', 'register', 'cashier'], 'What each payment method took for POS sales in a period, and paid back for returns.', 'pos_sales'],
        'pos-average-order'        => ['Average Order Value', 'pos', PosAverageOrderReport::class, ['period', 'group_by', 'location', 'register', 'cashier'], 'What a POS sale comes to on average, and how many items it has - by day, week, month, location, register or cashier.', 'pos_sales'],
        // Phase H2's: discounts, taxes, returns, what was called off, and items - the margin for those who see costs.
        'pos-discounts'            => ['Discounts and Price Overrides', 'pos', PosDiscountsReport::class, ['period', 'location', 'register', 'cashier'], 'POS sales that gave a discount or had a price overridden, why, and who approved a discount beyond the limit.', 'pos_sales'],
        'pos-taxes'                => ['Taxes Collected', 'pos', PosTaxesReport::class, ['period', 'location', 'register', 'cashier'], 'The tax POS sales charged, less what their returns gave back, by rate.', 'pos_taxes'],
        'pos-returns'              => ['Returns and Refunds', 'pos', PosReturnsReport::class, ['period', 'location', 'register', 'cashier'], 'POS returns of a period, what each credited, and how it was paid back.', 'pos_returns'],
        'pos-voids'                => ['Void and Cancellation Report', 'pos', PosVoidsReport::class, ['period', 'location', 'register', 'cashier'], 'Held orders cancelled or expired, and payments and refunds declined - who called each off, and why.', 'pos_sales'],
        'pos-top-items'            => ['Top-Selling Items', 'pos', PosTopItemsReport::class, ['period', 'location', 'register', 'cashier', 'category'], 'What POS sold most, by quantity, less what came back.', 'pos_sales'],
        'pos-margin'               => ['Estimated POS Margin', 'pos', PosMarginReport::class, ['period', 'location', 'register', 'cashier', 'category'], "POS sales by item less each item's cost now - an estimate, not a Profit and Loss Statement.", 'pos_costs'],
        // Phase H3's: sessions and cash.
        'pos-sessions'             => ['Session Summary', 'pos', PosSessionsReport::class, ['period', 'location', 'register', 'cashier'], 'The sessions opened in a period - what each took, the cash it should have held, what was counted, and the difference.', 'pos_sessions'],
        'pos-cash-movements'       => ['Cash Movement Report', 'pos', PosCashMovementsReport::class, ['period', 'location', 'register', 'cashier'], 'Cash put into and taken out of the drawers in a period - each kind, where it went, why, and who approved it.', 'pos_cash'],
        'pos-cash-variances'       => ['Cash Variance Report', 'pos', PosCashVariancesReport::class, ['period', 'location', 'register', 'cashier'], 'Sessions closed over or short in a period, why, and who approved the difference.', 'pos_sessions'],
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
        protected CrmSettingRepository $crmSettings,
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
            // CRM's (Doc 17 section 17.29): while it is on - the leads' while the business uses them.
            'crm_leads'         => $apps->has('crm') && $this->crmSettings->forWorkspace($workspace)->usesLeads(),
            'crm_opportunities' => $apps->has('crm'),
            'crm_activities'    => $apps->has('crm'),
            // POS's (Doc 18 section 18.36): while the business has its records.
            ...array_fill_keys(array_keys(self::POS), $apps->hasHistory('pos')),
            // Taxes Collected while taxes are on, or sales kept tax (Phase H2).
            'pos_taxes'         => $apps->hasHistory('pos') && ($workspace->usesTaxes() || $this->salesReportRepository->hasTaxHistory($workspace)),
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
            // The full Sales app's own count as Sales', marked as the full edition's (Doc 16 Phase H2a); CRM's as CRM's (Doc 17 Phase H1), POS's as POS's (Doc 18 Phase H1).
            $edition = array_key_exists($needs ?? '', self::FULL_SALES) ? 'full' : null;
            $needs   = $edition ? 'sales' : (array_key_exists($needs ?? '', self::CRM) ? 'crm' : (array_key_exists($needs ?? '', self::POS) ? 'pos' : $needs));

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
            'sales_full', 'sales_costs', 'sales_lists', 'sales_quotes', 'sales_orders' => $this->sees(['sales' => true]) && (self::FULL_SALES[$needs] === null || $this->access->can(self::FULL_SALES[$needs])),
            'sales_deliveries', 'sales_returns' => $this->access->can(self::FULL_SALES[$needs]),
            'crm_leads', 'crm_opportunities', 'crm_activities' => $this->access->can('crm.reports.view') && $this->access->can(self::CRM[$needs]),
            'pos_sales', 'pos_returns', 'pos_taxes', 'pos_costs', 'pos_sessions', 'pos_cash' => $this->access->can('pos.reports.view') && collect(self::POS[$needs])->every(fn (string $permission) => $this->access->can($permission)),
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
