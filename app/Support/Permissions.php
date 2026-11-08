<?php

namespace App\Support;

/**
 * What a role can allow in a business (Doc 12 sections 12 to 14): permission
 * groups - the Core Workspace's, and each app's - each with its actions. A
 * permission is "group.action": "contacts.export", "sales.invoices.issue".
 * An app's groups count while the app is on (section 15); Lite and full
 * editions share them, so a full app takes over its Lite permissions.
 *
 * An action can need others (section 13: "Issue Invoice requires View and
 * Edit Draft"). expand() adds them, so a saved role always holds what its
 * permissions need: every action needs its group's View, Issue and Record
 * need Edit Draft, a draft needs the contacts and items it is made from, and
 * a payment the record it pays and the payment accounts.
 *
 * An app's values - amounts, prices, totals - have a View of their own
 * (section 14): a role without it sees the app's records with their values
 * hidden. Anything more than viewing such a record - making, changing,
 * sending, paying, exporting - needs its values in sight. So do items'
 * purchase prices, payment accounts' balances and full details, and tax
 * information: a role sees them only with their own permission, and needs it
 * to change what holds them. The Activity Log, which records them all as they
 * were, needs every one of them.
 *
 * ALL stands for every permission there is, apps turned on later included:
 * what the Business Owner and a role with full access hold.
 */
final class Permissions
{
    public const ALL = '*';

    private const VIEW = 'View';

    private const DRAFTS = ['view' => self::VIEW, 'create' => 'Create Draft', 'edit' => 'Edit Draft'];

    private const PAYMENTS = ['view' => self::VIEW, 'record' => 'Record Payment', 'reverse' => 'Reverse Payment'];

    private const ATTACHMENTS = ['view' => self::VIEW, 'manage' => 'Add or Remove'];

    private const APP_SETTINGS = ['manage' => 'Manage Application Settings'];

    /** group => [label, app family (null: Core Workspace), [action => label]], in the order a role shows them. */
    public const GROUPS = [
        'dashboard'        => ['Dashboard', null, ['view' => self::VIEW]],
        'contacts'         => ['Contacts', null, ['view' => self::VIEW, 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'import' => 'Import', 'export' => 'Export']],
        'items'            => ['Items', null, ['view' => self::VIEW, 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'import' => 'Import', 'export' => 'Export', 'costs' => 'View Purchase Prices or Costs']],
        'payment_accounts' => ['Payment Accounts', null, [
            'view' => self::VIEW, 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'transfer' => 'Transfer Money', 'export' => 'Export',
            'balances' => 'View Balances', 'details' => 'View Full Account Details',
        ]],
        'documents'        => ['Documents', null, ['view' => self::VIEW, 'export' => 'Export']],
        'reports'          => ['Reports', null, ['view' => 'View Reports', 'export' => 'Export']],
        'taxes'            => ['Tax Information', null, ['view' => 'View Tax Information']],
        'settings'         => ['Settings', null, [
            'view'         => self::VIEW,
            'business'     => 'Manage Business Settings',
            'locations'    => 'Manage Locations',
            'currency_tax' => 'Manage Currency and Tax Settings',
            'numbering'    => 'Manage Document Numbering',
            'users'        => 'Manage Users and Roles',
            'apps'         => 'Manage Apps',
            'subscription' => 'Manage Subscription',
            'audit'        => 'View Activity Log',
        ]],

        'sales.quotations'   => ['Quotations', 'sales', [...self::DRAFTS, 'send' => 'Send or Share', 'cancel' => 'Cancel', 'delete' => 'Delete Draft']],
        'sales.proformas'    => ['Proforma Invoices', 'sales', [...self::DRAFTS, 'send' => 'Send or Share', 'cancel' => 'Cancel', 'delete' => 'Delete Draft']],
        'sales.invoices'     => ['Invoices', 'sales', [...self::DRAFTS, 'issue' => 'Issue', 'send' => 'Send or Share', 'void' => 'Void', 'delete' => 'Delete Draft']],
        'sales.payments'     => ['Customer Payments', 'sales', self::PAYMENTS],
        'sales.credit_notes' => ['Credit Notes', 'sales', [...self::DRAFTS, 'issue' => 'Issue', 'send' => 'Send or Share', 'void' => 'Void', 'delete' => 'Delete Draft']],
        'sales.refunds'      => ['Refunds', 'sales', ['record' => 'Record Refund']],
        'sales.values'       => ['Sales Values', 'sales', ['view' => 'View Sales Values']],
        'sales.settings'     => ['Sales Settings', 'sales', self::APP_SETTINGS],

        'purchases.purchases'   => ['Purchases', 'purchases', [...self::DRAFTS, 'record' => 'Record', 'void' => 'Void', 'delete' => 'Delete Draft', 'export' => 'Export']],
        'purchases.payments'    => ['Supplier Payments', 'purchases', self::PAYMENTS],
        'purchases.attachments' => ['Purchase Attachments', 'purchases', self::ATTACHMENTS],
        'purchases.values'      => ['Purchase Values', 'purchases', ['view' => 'View Purchase Values']],
        'purchases.settings'    => ['Purchase Settings', 'purchases', self::APP_SETTINGS],

        'expenses.expenses'    => ['Expenses', 'expenses', [...self::DRAFTS, 'record' => 'Record', 'void' => 'Void', 'delete' => 'Delete Draft', 'export' => 'Export']],
        'expenses.categories'  => ['Expense Categories', 'expenses', ['view' => self::VIEW, 'manage' => 'Add or Change']],
        'expenses.payments'    => ['Expense Payments', 'expenses', self::PAYMENTS],
        'expenses.attachments' => ['Receipt Attachments', 'expenses', self::ATTACHMENTS],
        'expenses.values'      => ['Expense Values', 'expenses', ['view' => 'View Expense Values']],
        'expenses.settings'    => ['Expense Settings', 'expenses', self::APP_SETTINGS],
    ];

    /** The groups whose records carry their app's values: anything but viewing one needs them. */
    private const VALUED = [
        'sales.quotations', 'sales.proformas', 'sales.invoices', 'sales.payments', 'sales.credit_notes', 'sales.refunds',
        'purchases.purchases', 'purchases.payments',
        'expenses.expenses', 'expenses.payments',
    ];

    /** What a permission needs besides its group's View (and Edit Draft, for Issue and Record). */
    private const REQUIRES = [
        // A form or a file holds what it changes: a contact's tax details, an item's purchase price, an account's balance and numbers.
        'contacts.create'            => ['taxes.view'],
        'contacts.edit'              => ['taxes.view'],
        'contacts.import'            => ['taxes.view'],
        'items.create'               => ['items.costs'],
        'items.edit'                 => ['items.costs'],
        'items.import'               => ['items.costs'],
        'payment_accounts.create'    => ['payment_accounts.balances', 'payment_accounts.details'],
        'payment_accounts.edit'      => ['payment_accounts.balances', 'payment_accounts.details'],
        'payment_accounts.transfer'  => ['payment_accounts.balances'],
        'payment_accounts.export'    => ['payment_accounts.balances'],
        'settings.currency_tax'      => ['taxes.view'],
        // The Activity Log writes down what was changed and paid - amounts, prices, balances, numbers and tax details as they were.
        'settings.audit'             => [
            'items.costs', 'payment_accounts.balances', 'payment_accounts.details', 'taxes.view',
            'sales.values.view', 'purchases.values.view', 'expenses.values.view',
        ],

        'sales.quotations.create'    => ['contacts.view', 'items.view'],
        'sales.proformas.create'     => ['contacts.view', 'items.view'],
        'sales.invoices.create'      => ['contacts.view', 'items.view'],
        'sales.credit_notes.create'  => ['sales.invoices.view'],
        'sales.payments.record'      => ['sales.invoices.view', 'payment_accounts.view'],
        'sales.payments.reverse'     => ['payment_accounts.view'],
        'sales.refunds.record'       => ['sales.credit_notes.view', 'payment_accounts.view'],
        'sales.settings.manage'      => ['settings.view'],
        'purchases.purchases.create' => ['contacts.view', 'items.view'],
        'purchases.payments.record'  => ['purchases.purchases.view', 'payment_accounts.view'],
        'purchases.payments.reverse' => ['payment_accounts.view'],
        'purchases.settings.manage'  => ['settings.view'],
        'expenses.expenses.create'   => ['contacts.view', 'expenses.categories.view'],
        'expenses.payments.record'   => ['expenses.expenses.view', 'payment_accounts.view'],
        'expenses.payments.reverse'  => ['payment_accounts.view'],
        'expenses.settings.manage'   => ['settings.view'],
    ];

    /** The default role templates (Doc 12 section 11): key => [name, description]. */
    public const TEMPLATES = [
        'owner'         => ['Business Owner', 'Full Workspace, security, subscription and ownership access.'],
        'administrator' => ['Administrator', 'Full operational and Settings access, except ownership transfer.'],
        'manager'       => ['Manager', 'Operational management, Dashboard and Reports access, without ownership or security administration.'],
        'sales_user'    => ['Sales User', 'Contacts, Items and permitted Sales actions.'],
        'purchase_user' => ['Purchase User', 'Supplier Contacts, Items and permitted Purchase actions.'],
        'expense_user'  => ['Expense User', 'Permitted Expense creation, recording and payment actions.'],
        'finance_user'  => ['Finance User', 'Payment Accounts, payments, tax information and permitted financial reports.'],
        'view_only'     => ['View Only', 'Read-only access to selected applications, records and Locations.'],
    ];

    /** Templates that hold every permission, apps turned on later included. */
    public const FULL_ACCESS_TEMPLATES = ['owner', 'administrator'];

    /**
     * Every permission of the Core Workspace and of $families' apps.
     *
     * @param  list<string>|null  $families  null for every app's
     * @return list<string>
     */
    public static function keys(?array $families = null): array
    {
        $keys = [];

        foreach (self::GROUPS as $group => [, $family, $actions]) {
            if ($family === null || $families === null || in_array($family, $families, true)) {
                foreach (array_keys($actions) as $action) {
                    $keys[] = "{$group}.{$action}";
                }
            }
        }

        return $keys;
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::keys(), true);
    }

    /** The app a permission belongs to, null for the Core Workspace's. */
    public static function familyOf(string $permission): ?string
    {
        return self::GROUPS[self::groupOf($permission)][1] ?? null;
    }

    /**
     * What $permission needs, its own needs followed through: "sales.invoices.issue"
     * needs sales.invoices.view and sales.invoices.edit.
     *
     * @return list<string>
     */
    public static function requires(string $permission): array
    {
        return array_values(array_diff(self::expand([$permission]), [$permission]));
    }

    /**
     * $permissions with everything they need, known ones only, in catalogue order.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function expand(array $permissions): array
    {
        $held  = [];
        $queue = array_values(array_filter($permissions, [self::class, 'exists']));

        while ($queue) {
            $permission = array_shift($queue);

            if (isset($held[$permission])) {
                continue;
            }

            $held[$permission] = true;
            $queue             = [...$queue, ...self::directNeeds($permission)];
        }

        return array_values(array_filter(self::keys(), fn (string $key) => isset($held[$key])));
    }

    /**
     * A template's permissions among the Core Workspace's and $families' -
     * an app turned on later gives a role nothing until someone grants it
     * (Doc 12 section 15). The full-access templates hold ALL instead.
     *
     * @param  list<string>  $families
     * @return list<string>
     */
    public static function forTemplate(string $template, array $families): array
    {
        if (in_array($template, self::FULL_ACCESS_TEMPLATES, true)) {
            return [];
        }

        $available = self::keys($families);

        $chosen = match ($template) {
            // Everything but Settings, the business's and the apps'.
            'manager' => array_filter($available, fn (string $key) => self::groupOf($key) !== 'settings' && ! str_ends_with(self::groupOf($key), '.settings')),

            'sales_user' => [
                'dashboard.view', 'contacts.view', 'contacts.create', 'contacts.edit', 'items.view', 'documents.view',
                ...self::actionsOf('sales.quotations', ['view', 'create', 'edit', 'send', 'cancel', 'delete']),
                ...self::actionsOf('sales.proformas', ['view', 'create', 'edit', 'send', 'cancel', 'delete']),
                ...self::actionsOf('sales.invoices', ['view', 'create', 'edit', 'issue', 'send', 'delete']),
                'sales.payments.view', 'sales.payments.record', 'sales.credit_notes.view',
            ],

            'purchase_user' => [
                'dashboard.view', 'contacts.view', 'contacts.create', 'contacts.edit', 'items.view', 'items.costs', 'documents.view',
                ...self::actionsOf('purchases.purchases', ['view', 'create', 'edit', 'record', 'delete']),
                'purchases.payments.view', 'purchases.payments.record', 'purchases.attachments.view', 'purchases.attachments.manage',
            ],

            'expense_user' => [
                'dashboard.view', 'contacts.view', 'documents.view',
                ...self::actionsOf('expenses.expenses', ['view', 'create', 'edit', 'record', 'delete']),
                'expenses.categories.view', 'expenses.payments.view', 'expenses.payments.record',
                'expenses.attachments.view', 'expenses.attachments.manage',
            ],

            'finance_user' => [
                'dashboard.view', 'contacts.view', 'documents.view', 'reports.view', 'reports.export', 'taxes.view',
                ...self::actionsOf('payment_accounts', ['view', 'create', 'edit', 'transfer', 'export', 'balances', 'details']),
                'sales.invoices.view', 'sales.credit_notes.view', 'sales.payments.view', 'sales.payments.record', 'sales.payments.reverse', 'sales.refunds.record',
                'purchases.purchases.view', 'purchases.payments.view', 'purchases.payments.record', 'purchases.payments.reverse',
                'expenses.expenses.view', 'expenses.payments.view', 'expenses.payments.record', 'expenses.payments.reverse',
            ],

            // Every View, Settings and the apps' settings aside.
            'view_only' => array_filter($available, fn (string $key) => str_ends_with($key, '.view') && self::groupOf($key) !== 'settings'),

            default => [],
        };

        return self::expand(array_values(array_intersect($chosen, $available)));
    }

    /**
     * The groups a role shows for $families' apps, as the role editor draws
     * them: each permission with its label and what it needs.
     *
     * @param  list<string>  $families
     * @return list<array{key: string, label: string, family: string|null, permissions: list<array{key: string, label: string, requires: list<string>}>}>
     */
    public static function groups(array $families): array
    {
        $groups = [];

        foreach (self::GROUPS as $group => [$label, $family, $actions]) {
            if ($family !== null && ! in_array($family, $families, true)) {
                continue;
            }

            $groups[] = [
                'key'         => $group,
                'label'       => $label,
                'family'      => $family,
                'permissions' => array_map(fn (string $action) => [
                    'key'      => "{$group}.{$action}",
                    'label'    => $actions[$action],
                    'requires' => self::requires("{$group}.{$action}"),
                ], array_keys($actions)),
            ];
        }

        return $groups;
    }

    /**
     * The permission groups app $family brings to roles (Doc 13 section 6),
     * by their labels: "Quotations", "Invoices", "Sales Values"…
     *
     * @return list<string>
     */
    public static function groupLabelsOf(string $family): array
    {
        return array_values(array_map(fn (array $group) => $group[0], array_filter(self::GROUPS, fn (array $group) => $group[1] === $family)));
    }

    /** A permission's label as one line of the Activity Log: "Invoices: Issue". */
    public static function label(string $permission): string
    {
        $group  = self::groupOf($permission);
        $action = substr($permission, strlen($group) + 1);

        return isset(self::GROUPS[$group][2][$action]) ? self::GROUPS[$group][0].': '.self::GROUPS[$group][2][$action] : $permission;
    }

    /** @return list<string> */
    private static function directNeeds(string $permission): array
    {
        $group   = self::groupOf($permission);
        $action  = substr($permission, strlen($group) + 1);
        $actions = self::GROUPS[$group][2];
        $needs   = self::REQUIRES[$permission] ?? [];

        if ($action !== 'view' && isset($actions['view'])) {
            $needs[] = "{$group}.view";
        }

        if (in_array($action, ['issue', 'record'], true) && isset($actions['edit'])) {
            $needs[] = "{$group}.edit";
        }

        if ($action !== 'view' && in_array($group, self::VALUED, true)) {
            $needs[] = self::GROUPS[$group][1].'.values.view';
        }

        return $needs;
    }

    /** "sales.invoices.issue" is in group sales.invoices; "contacts.view" in contacts. */
    private static function groupOf(string $permission): string
    {
        return substr($permission, 0, (int) strrpos($permission, '.'));
    }

    /** @return list<string> */
    private static function actionsOf(string $group, array $actions): array
    {
        return array_map(fn (string $action) => "{$group}.{$action}", $actions);
    }
}
