<?php

namespace Database\Seeders;

use App\Enums\Edition;
use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\AppDependency;
use App\Models\Frontend\Application;
use App\Models\Frontend\MenuItem;
use App\Models\User\Workspace;
use App\Services\User\Apps\ProvisionWorkspaceApps;
use Illuminate\Database\Seeder;

/**
 * The starting app catalog (Doc 01 sections 3, 5, 6 and 7): the Apps Center's
 * categories and apps, which plan includes which app, what apps need from each
 * other, and the business sidebar.
 *
 * Only Sales, Purchases and Expenses (Lite and Full), CRM and Inventory are
 * available; the rest are listed as coming soon until their modules exist.
 * Which apps sit in Standard and which only in Premium is a starting point -
 * Doc 01 leaves it open - and is changed in the admin panel's Package Access.
 *
 * Safe to run again, including on a live server
 * (php artisan db:seed --class=AppCatalogSeeder): it only makes what is
 * missing, never overwrites what an admin has changed, and finishes by giving
 * existing businesses their plan's Lite apps where they have no row yet.
 */
class AppCatalogSeeder extends Seeder
{
    /** family => [name, icon, lite name or null, status, standard?, description] by category */
    private const CATALOG = [
        'Sales & Commerce' => ['icon' => 'shopping-bag', 'apps' => [
            'sales'            => ['Sales', 'receipt', 'Sales Lite', 'available', true, 'Quotations, proforma invoices, invoices and sales orders.'],
            'crm'              => ['CRM', 'contact', null, 'available', true, 'Leads, deals and the pipeline that turns them into customers.'],
            'pos'              => ['Point of Sale', 'scan-barcode', null, 'coming_soon', true, 'Sell over the counter, with receipts and cash sessions.'],
            'ecommerce'        => ['eCommerce', 'shopping-cart', null, 'coming_soon', false, 'An online store that shares your items and stock.'],
            'subscriptions'    => ['Subscriptions', 'repeat', null, 'coming_soon', false, 'Recurring plans and automatic invoicing.'],
            'marketing'        => ['Marketing', 'megaphone', null, 'coming_soon', false, 'Campaigns and audiences built from your contacts.'],
            'loyalty'          => ['Loyalty', 'gift', null, 'coming_soon', false, 'Points and rewards for returning customers.'],
            'business-profile' => ['Digital Business Profile', 'id-card', null, 'coming_soon', true, 'A public page for your business.'],
        ]],
        'Finance & Accounting' => ['icon' => 'landmark', 'apps' => [
            'expenses'       => ['Expenses', 'wallet', 'Expenses Lite', 'available', true, 'Record spending, receipts and recurring costs.'],
            'accounting'     => ['Accounting', 'book-open', null, 'coming_soon', false, 'Ledgers, journals and financial statements.'],
            'banking'        => ['Banking', 'building-2', null, 'coming_soon', true, 'Bank feeds and reconciliation.'],
            'tax-management' => ['Tax Management', 'percent', null, 'coming_soon', true, 'Tax returns and filings for your country.'],
            'budgets'        => ['Budgets', 'piggy-bank', null, 'coming_soon', false, 'Plan spending and track it against actuals.'],
            'cash-flow'      => ['Cash Flow Planning', 'trending-up', null, 'coming_soon', false, 'Forecast what comes in and goes out.'],
        ]],
        'Purchasing & Inventory' => ['icon' => 'boxes', 'apps' => [
            'purchases'     => ['Purchases', 'shopping-basket', 'Purchases Lite', 'available', true, 'Bills, purchase orders and vendor credits.'],
            'inventory'     => ['Inventory', 'package', null, 'available', true, 'Stock levels, movements and adjustments.'],
            'warehouses'    => ['Warehouses', 'warehouse', null, 'coming_soon', false, 'Stock across several warehouses, with transfers.'],
            'barcode'       => ['Barcode', 'barcode', null, 'coming_soon', true, 'Scan items in and out.'],
            'manufacturing' => ['Manufacturing', 'factory', null, 'coming_soon', false, 'Bills of materials and production orders.'],
            'quality'       => ['Quality', 'badge-check', null, 'coming_soon', false, 'Inspections and quality checks.'],
            'assets'        => ['Assets', 'archive', null, 'coming_soon', false, 'Fixed assets and depreciation.'],
            'maintenance'   => ['Maintenance', 'wrench', null, 'coming_soon', false, 'Planned and on-demand maintenance.'],
            'fleet'         => ['Fleet', 'truck', null, 'coming_soon', false, 'Vehicles, drivers and running costs.'],
        ]],
        'People & Payroll' => ['icon' => 'users', 'apps' => [
            'employees'   => ['Employees', 'users', null, 'coming_soon', true, 'Employee records and documents.'],
            'attendance'  => ['Attendance', 'clock', null, 'coming_soon', true, 'Check-ins, check-outs and timesheets.'],
            'leave'       => ['Leave', 'calendar-off', null, 'coming_soon', true, 'Leave requests and balances.'],
            'shifts'      => ['Shifts', 'calendar-clock', null, 'coming_soon', false, 'Rosters and shift planning.'],
            'payroll'     => ['Payroll', 'banknote', null, 'coming_soon', false, 'Salaries, payslips and payroll runs.'],
            'recruitment' => ['Recruitment', 'user-plus', null, 'coming_soon', false, 'Job openings and applicants.'],
            'performance' => ['Performance', 'gauge', null, 'coming_soon', false, 'Goals and reviews.'],
        ]],
        'Projects, Services & Support' => ['icon' => 'briefcase', 'apps' => [
            'projects'      => ['Projects', 'folder-kanban', null, 'coming_soon', true, 'Projects, milestones and budgets.'],
            'tasks'         => ['Tasks', 'list-checks', null, 'coming_soon', true, 'Task lists and assignments.'],
            'timesheets'    => ['Timesheets', 'timer', null, 'coming_soon', false, 'Time tracked against projects and tasks.'],
            'helpdesk'      => ['Helpdesk', 'life-buoy', null, 'coming_soon', true, 'Support tickets and replies.'],
            'field-service' => ['Field Service', 'map-pin', null, 'coming_soon', false, 'Jobs and technicians on site.'],
            'appointments'  => ['Appointments', 'calendar-check', null, 'coming_soon', true, 'Bookings and calendars.'],
        ]],
        'Documents & Workflow' => ['icon' => 'file-stack', 'apps' => [
            'document-management' => ['Document Management', 'folder-open', null, 'coming_soon', true, 'Files, folders and versions.'],
            'approvals'           => ['Approvals', 'check-check', null, 'coming_soon', false, 'Approval steps for documents and spending.'],
            'workflow-automation' => ['Workflow Automation', 'workflow', null, 'coming_soon', false, 'Rules that act when something happens.'],
            'e-signatures'        => ['Electronic Signatures', 'signature', null, 'coming_soon', false, 'Send documents to be signed.'],
            'ocr'                 => ['OCR', 'scan-text', null, 'coming_soon', false, 'Read bills and receipts from photos.'],
        ]],
        'Analytics, AI & Integrations' => ['icon' => 'sparkles', 'apps' => [
            'advanced-reports' => ['Advanced Reports', 'chart-line', null, 'coming_soon', true, 'Custom reports and dashboards.'],
            'ai-assistant'     => ['AI Assistant', 'bot', null, 'coming_soon', false, 'Ask questions about your business.'],
            'automation'       => ['Automation', 'zap', null, 'coming_soon', false, 'Scheduled and triggered automations.'],
            'integrations'     => ['Integrations', 'plug', null, 'coming_soon', false, 'Connect the tools you already use.'],
            'api-webhooks'     => ['API & Webhooks', 'webhook', null, 'coming_soon', false, 'Programmatic access and event hooks.'],
        ]],
    ];

    /**
     * Sort order is shared by the sidebar and the Apps Center. These three come
     * first, in the order Doc 01's sidebar lists them; the rest follow the
     * catalog.
     */
    private const FIRST = ['sales' => 10, 'purchases' => 20, 'expenses' => 30];

    /** family => [required family => minimum edition] */
    private const DEPENDENCIES = [
        'inventory'     => ['purchases' => 'lite'],
        'pos'           => ['sales' => 'lite'],
        'warehouses'    => ['inventory' => 'lite'],
        'barcode'       => ['inventory' => 'lite'],
        'manufacturing' => ['inventory' => 'lite'],
        'attendance'    => ['employees' => 'lite'],
        'leave'         => ['employees' => 'lite'],
        'shifts'        => ['employees' => 'lite'],
        'payroll'       => ['employees' => 'lite'],
        'timesheets'    => ['projects' => 'lite'],
    ];

    /** [section, family, label, icon, route, min edition, show if] in order */
    private const MENU = [
        ['core', null, 'Dashboard', 'layout-dashboard', '/dashboard', null, null],
        ['core', null, 'Contacts', 'users', '/contacts', null, null],
        ['core', null, 'Items', 'package', '/items', null, null],
        ['core', null, 'Payment Accounts', 'landmark', '/payment-accounts', null, 'has_payment_account'],
        ['core', null, 'Documents', 'files', '/documents', null, null],
        ['core', null, 'Reports', 'chart-column', '/reports', null, null],

        ['apps', 'sales', 'Invoices', 'receipt', '/sales/invoices', 'lite', null],
        ['apps', 'sales', 'Quotations', 'file-text', '/sales/quotations', 'lite', null],
        ['apps', 'sales', 'Proforma Invoices', 'file-check', '/sales/proformas', 'lite', null],
        ['apps', 'sales', 'Credit Notes', 'receipt-text', '/sales/credit-notes', 'lite', null],
        ['apps', 'sales', 'Payments Received', 'hand-coins', '/sales/payments', 'lite', null],
        ['apps', 'sales', 'Sales Orders', 'clipboard-list', '/sales/orders', 'full', null],
        ['apps', 'purchases', 'Bills', 'file-minus', '/purchases/bills', 'lite', null],
        ['apps', 'purchases', 'Purchase Orders', 'clipboard-list', '/purchases/orders', 'full', null],
        ['apps', 'expenses', 'Expenses', 'wallet', '/expenses', 'lite', null],
        ['apps', 'expenses', 'Recurring Expenses', 'repeat', '/expenses/recurring', 'full', null],
        ['apps', 'crm', 'Leads', 'target', '/crm/leads', 'lite', null],
        ['apps', 'crm', 'Deals', 'handshake', '/crm/deals', 'lite', null],
        ['apps', 'inventory', 'Stock', 'boxes', '/inventory/stock', 'lite', null],
        ['apps', 'inventory', 'Adjustments', 'sliders-horizontal', '/inventory/adjustments', 'lite', null],

        ['bottom', null, 'Workspaces', 'building-2', '/workspace', null, null],
        ['bottom', null, 'Staff', 'user-check', '/staff', null, null],
        ['bottom', null, 'Apps Center', 'layout-grid', '/apps-center', null, null],
        ['bottom', null, 'Settings', 'settings', '/settings', null, null],
    ];

    public function run(): void
    {
        $packages = Package::orderBy('rank')->get()->keyBy('key');

        $categoryOrder = 0;
        $appOrder      = 100;

        foreach (self::CATALOG as $categoryName => $category) {
            $categoryModel = AppCategory::firstOrCreate(
                ['name' => $categoryName],
                ['icon' => $category['icon'], 'sort_order' => $categoryOrder += 10, 'is_active' => true],
            );

            foreach ($category['apps'] as $family => [$name, $icon, $liteName, $status, $inStandard, $description]) {
                $sortOrder = self::FIRST[$family] ?? ($appOrder += 10);

                $editions = $liteName ? [Edition::Lite->value => $liteName, Edition::Full->value => $name] : [Edition::Full->value => $name];

                foreach ($editions as $edition => $editionName) {
                    $app = Application::firstOrCreate(
                        ['app_key' => $family.($edition === Edition::Lite->value ? '_lite' : '')],
                        [
                            'name'        => $editionName,
                            'description' => $description,
                            'category_id' => $categoryModel->id,
                            'icon'        => $icon,
                            'status'      => $status,
                            'is_active'   => true,
                            'family_key'  => $family,
                            'edition'     => $edition,
                            'sort_order'  => $sortOrder,
                        ],
                    );

                    // Plan access only for an app made just now: an admin's
                    // later changes in Package Access are left alone.
                    if ($app->wasRecentlyCreated) {
                        $lowest = match (true) {
                            $edition === Edition::Lite->value => 'starter',
                            $inStandard                       => 'standard',
                            default                           => 'premium',
                        };

                        $app->packages()->syncWithoutDetaching(
                            $packages->filter(fn (Package $p) => $p->rank >= $packages[$lowest]->rank)->pluck('id')->all()
                        );
                    }
                }
            }
        }

        foreach (self::DEPENDENCIES as $family => $needs) {
            foreach (Application::where('family_key', $family)->get() as $app) {
                foreach ($needs as $requiredFamily => $minEdition) {
                    AppDependency::firstOrCreate(
                        ['app_id' => $app->id, 'requires_family' => $requiredFamily],
                        ['min_edition' => $minEdition],
                    );
                }
            }
        }

        $menuOrder = 0;

        foreach (self::MENU as [$section, $family, $label, $icon, $route, $minEdition, $showIf]) {
            MenuItem::firstOrCreate(
                ['section' => $section, 'family_key' => $family, 'route' => $route],
                [
                    'label'       => $label,
                    'icon'        => $icon,
                    'min_edition' => $minEdition,
                    'show_if'     => $showIf,
                    'sort_order'  => $menuOrder += 10,
                    'is_active'   => true,
                ],
            );
        }

        // Businesses made before the catalog existed get their Lite apps now.
        Workspace::with('package')->each(fn (Workspace $workspace) => app(ProvisionWorkspaceApps::class)->handle($workspace));
    }
}
