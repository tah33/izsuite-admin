<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The permission each business route needs (Doc 12 sections 12 to 14), by
 * route name - one, or a list of which any one will do. MEMBER is any member
 * of the business: the lists every form picks from (locations, tax rates,
 * payment terms), the sidebar, a file link - each file checked by what it is
 * attached to.
 *
 * In a permission, {kind} is the route's kind with dashes as underscores
 * (credit-notes: sales.credit_notes.view; contacts: contacts.import), and
 * {to} the kind a sales document is converted to (sales.invoices.create).
 *
 * A business route missing here is open to full access only (EnsurePermission);
 * RoutePermissionsTest lists any.
 */
final class RoutePermissions
{
    public const MEMBER = 'member';

    private const SALES_DRAFTS = [
        'sales.quotations.create', 'sales.quotations.edit', 'sales.proformas.create', 'sales.proformas.edit',
        'sales.invoices.create', 'sales.invoices.edit', 'sales.credit_notes.create', 'sales.credit_notes.edit',
    ];

    /** @return array<string, string|list<string>|Closure(Request): string> */
    public static function map(): array
    {
        return [
            'api.navigation'               => self::MEMBER,
            'api.dashboard'                => 'dashboard.view',
            'api.files.link'               => self::MEMBER,
            'api.business-settings.usage'  => self::MEMBER,

            // Contacts and items (Doc 03, Doc 04)
            'api.contacts.index'               => 'contacts.view',
            'api.contacts.show'                => 'contacts.view',
            'api.contacts.attachments.index'   => 'contacts.view',
            'api.contacts.store'               => 'contacts.create',
            'api.contacts.update'              => 'contacts.edit',
            'api.contacts.activate'            => 'contacts.edit',
            'api.contacts.deactivate'          => 'contacts.edit',
            'api.contacts.attachments.store'   => 'contacts.edit',
            'api.contacts.attachments.destroy' => 'contacts.edit',
            'api.contacts.destroy'             => 'contacts.delete',
            'api.contacts.export'              => 'contacts.export',

            'api.items.index'              => 'items.view',
            'api.items.show'               => 'items.view',
            'api.items.files.index'        => 'items.view',
            'api.items.transactions.index' => 'items.view',
            'api.items.store'              => 'items.create',
            'api.items.update'             => 'items.edit',
            'api.items.activate'           => 'items.edit',
            'api.items.deactivate'         => 'items.edit',
            'api.items.files.store'        => 'items.edit',
            'api.items.files.destroy'      => 'items.edit',
            'api.items.image.store'        => 'items.edit',
            'api.items.image.destroy'      => 'items.edit',
            'api.items.destroy'            => 'items.delete',
            'api.items.export'             => 'items.export',
            'api.item-labels.index'        => 'items.view',
            'api.item-labels.store'        => ['items.create', 'items.edit'],
            'api.item-labels.update'       => ['items.create', 'items.edit'],
            'api.item-labels.destroy'      => ['items.create', 'items.edit'],

            // Imports: the kind's own Import permission - checked by ImportService for an import already started.
            'api.imports.template' => '{kind}.import',
            'api.imports.store'    => fn (Request $request) => Str::lower((string) $request->input('kind')).'.import',
            'api.imports.show'     => self::MEMBER,
            'api.imports.map'      => self::MEMBER,
            'api.imports.rows'     => self::MEMBER,
            'api.imports.run'      => self::MEMBER,
            'api.imports.destroy'  => self::MEMBER,

            // Payment accounts and transfers (Doc 05)
            'api.payment-accounts.index'               => 'payment_accounts.view',
            'api.payment-accounts.show'                => 'payment_accounts.view',
            'api.payment-accounts.transactions.index'  => 'payment_accounts.view',
            'api.payment-accounts.store'               => 'payment_accounts.create',
            'api.payment-accounts.update'              => 'payment_accounts.edit',
            'api.payment-accounts.activate'            => 'payment_accounts.edit',
            'api.payment-accounts.deactivate'          => 'payment_accounts.edit',
            'api.payment-accounts.default'             => 'payment_accounts.edit',
            'api.payment-accounts.destroy'             => 'payment_accounts.delete',
            'api.payment-accounts.transactions.export' => 'payment_accounts.export',
            'api.account-transfers.show'               => 'payment_accounts.view',
            'api.account-transfers.store'              => 'payment_accounts.transfer',
            'api.account-transfers.update'             => 'payment_accounts.transfer',
            'api.account-transfers.destroy'            => 'payment_accounts.transfer',
            'api.account-transfers.complete'           => 'payment_accounts.transfer',
            'api.account-transfers.void'               => 'payment_accounts.transfer',

            // Documents and Reports (Doc 06, Doc 07)
            'api.documents.index'  => 'documents.view',
            'api.documents.export' => 'documents.export',
            'api.reports.index'    => 'reports.view',
            'api.reports.show'     => 'reports.view',
            'api.reports.pdf'      => 'reports.view',
            'api.reports.export'   => 'reports.export',
            'api.reports.excel'    => 'reports.export',

            // Settings (Doc 11): what forms pick from is open to every member.
            'api.business-settings.show'                       => self::MEMBER,
            'api.business-settings.save'                       => 'settings.business',
            'api.business-settings.regional.show'              => 'settings.view',
            'api.business-settings.regional.update'            => 'settings.business',
            'api.business-settings.items.show'                 => self::MEMBER,
            'api.business-settings.items.update'               => 'settings.business',
            'api.business-settings.email.show'                 => 'settings.view',
            'api.business-settings.email.update'               => 'settings.business',
            'api.business-settings.templates.index'            => 'settings.view',
            'api.business-settings.templates.update'           => 'settings.business',
            'api.business-settings.templates.preview'          => 'settings.business',
            'api.business-settings.templates.signature.store'  => 'settings.business',
            'api.business-settings.templates.signature.destroy' => 'settings.business',
            'api.business-settings.currencies.show'            => 'settings.view',
            'api.business-settings.currencies.update'          => 'settings.currency_tax',
            'api.business-settings.taxes.show'                 => 'settings.view',
            'api.business-settings.taxes.update'               => 'settings.currency_tax',
            'api.business-settings.numbering.index'            => 'settings.view',
            'api.business-settings.numbering.preview'          => 'settings.view',
            'api.business-settings.numbering.update'           => 'settings.numbering',
            'api.business-settings.sales.show'                 => 'settings.view',
            'api.business-settings.sales.update'               => 'sales.settings.manage',
            'api.business-settings.purchases.show'             => 'settings.view',
            'api.business-settings.purchases.update'           => 'purchases.settings.manage',
            'api.business-settings.expenses.show'              => 'settings.view',
            'api.business-settings.expenses.update'            => 'expenses.settings.manage',

            'api.setup.show'     => 'settings.business',
            'api.setup.update'   => 'settings.business',
            'api.setup.complete' => 'settings.business',

            'api.locations.index'        => self::MEMBER,
            'api.locations.store'        => 'settings.locations',
            'api.locations.update'       => 'settings.locations',
            'api.locations.destroy'      => 'settings.locations',
            'api.locations.activate'     => 'settings.locations',
            'api.locations.deactivate'   => 'settings.locations',
            'api.locations.primary'      => 'settings.locations',
            // Who may use a location is the users' to set (Doc 12 section 18: Assign Users).
            'api.locations.users.update' => 'settings.users',

            'api.payment-terms.index'   => self::MEMBER,
            'api.payment-terms.store'   => 'settings.business',
            'api.payment-terms.update'  => 'settings.business',
            'api.payment-terms.destroy' => 'settings.business',

            'api.tax-rates.index'           => self::MEMBER,
            'api.tax-rates.store'           => 'settings.currency_tax',
            'api.tax-rates.update'          => 'settings.currency_tax',
            'api.tax-rates.destroy'         => 'settings.currency_tax',
            'api.withholding-taxes.index'   => self::MEMBER,
            'api.withholding-taxes.store'   => 'settings.currency_tax',
            'api.withholding-taxes.update'  => 'settings.currency_tax',
            'api.withholding-taxes.destroy' => 'settings.currency_tax',
            'api.exchange-rates.index'      => self::MEMBER,
            'api.exchange-rates.store'      => 'settings.currency_tax',
            'api.exchange-rates.destroy'    => 'settings.currency_tax',

            'api.roles.index'        => 'settings.users',
            'api.roles.permissions'  => 'settings.users',
            'api.roles.show'         => 'settings.users',
            'api.roles.store'        => 'settings.users',
            'api.roles.update'       => 'settings.users',
            'api.roles.copy'         => 'settings.users',
            'api.roles.activate'     => 'settings.users',
            'api.roles.deactivate'   => 'settings.users',
            'api.roles.destroy'      => 'settings.users',
            'api.members.index'      => 'settings.users',
            'api.members.export'     => 'settings.users',
            'api.members.show'       => 'settings.users',
            'api.members.store'      => 'settings.users',
            'api.members.update'     => 'settings.users',
            'api.members.destroy'    => 'settings.users',
            'api.members.resend'     => 'settings.users',
            'api.members.suspend'    => 'settings.users',
            'api.members.reactivate' => 'settings.users',
            'api.members.remove'     => 'settings.users',
            'api.members.sign-out'   => 'settings.users',
            // Only the Business Owner hands the business over - MemberStatusService checks.
            'api.members.transfer'   => self::MEMBER,

            'api.apps-center'        => 'settings.apps',
            'api.apps.activate'      => 'settings.apps',
            'api.apps.deactivate'    => 'settings.apps',
            'api.activity-log.index' => 'settings.audit',
            'api.activity-log.users' => 'settings.audit',

            // Sales Lite (Doc 08): quotations, proforma invoices, invoices and credit notes by their kind.
            'api.sales.index'               => 'sales.{kind}.view',
            'api.sales.show'                => 'sales.{kind}.view',
            'api.sales.pdf'                 => 'sales.{kind}.view',
            'api.sales.revisions.index'     => 'sales.{kind}.view',
            'api.sales.revisions.show'      => 'sales.{kind}.view',
            'api.sales.attachments.index'   => 'sales.{kind}.view',
            'api.sales.store'               => 'sales.{kind}.create',
            'api.sales.defaults'            => ['sales.{kind}.create', 'sales.{kind}.edit'],
            'api.sales.calculate'           => self::SALES_DRAFTS,
            'api.sales.update'              => 'sales.{kind}.edit',
            'api.sales.accept'              => 'sales.{kind}.edit',
            'api.sales.reject'              => 'sales.{kind}.edit',
            'api.sales.attachments.store'   => 'sales.{kind}.edit',
            'api.sales.attachments.destroy' => 'sales.{kind}.edit',
            'api.sales.issue'               => 'sales.{kind}.issue',
            'api.sales.send'                => 'sales.{kind}.send',
            'api.sales.email'               => 'sales.{kind}.send',
            'api.sales.email-draft'         => 'sales.{kind}.send',
            'api.sales.link.store'          => 'sales.{kind}.send',
            'api.sales.link.destroy'        => 'sales.{kind}.send',
            'api.sales.cancel'              => 'sales.{kind}.cancel',
            'api.sales.void'                => 'sales.{kind}.void',
            'api.sales.destroy'             => 'sales.{kind}.delete',
            'api.sales.convert'             => 'sales.{to}.create',

            'api.sales.credit-notes.defaults'       => 'sales.credit_notes.create',
            'api.sales.credit-notes.store'          => 'sales.credit_notes.create',
            'api.sales.credit-notes.calculate'      => ['sales.credit_notes.create', 'sales.credit_notes.edit'],
            'api.sales.credit-notes.update'         => 'sales.credit_notes.edit',
            'api.sales.credit-notes.refunds.store'  => 'sales.refunds.record',
            'api.sales.invoices.payments.store'     => 'sales.payments.record',
            'api.sales.payments.index'              => 'sales.payments.view',
            'api.sales.payments.show'               => 'sales.payments.view',
            'api.sales.payments.pdf'                => 'sales.payments.view',
            'api.sales.payments.attachments.index'  => 'sales.payments.view',
            'api.sales.payments.attachments.store'  => 'sales.payments.record',
            'api.sales.payments.attachments.destroy' => 'sales.payments.record',
            'api.sales.payments.email'              => 'sales.payments.record',
            'api.sales.payments.email-draft'        => 'sales.payments.record',
            'api.sales.payments.reverse'            => 'sales.payments.reverse',

            // Purchases Lite (Doc 09)
            'api.purchases.index'                      => 'purchases.purchases.view',
            'api.purchases.show'                       => 'purchases.purchases.view',
            'api.purchases.pdf'                        => 'purchases.purchases.view',
            'api.purchases.store'                      => 'purchases.purchases.create',
            'api.purchases.defaults'                   => ['purchases.purchases.create', 'purchases.purchases.edit'],
            'api.purchases.calculate'                  => ['purchases.purchases.create', 'purchases.purchases.edit'],
            'api.purchases.update'                     => 'purchases.purchases.edit',
            'api.purchases.record'                     => 'purchases.purchases.record',
            'api.purchases.void'                       => 'purchases.purchases.void',
            'api.purchases.destroy'                    => 'purchases.purchases.delete',
            'api.purchases.export'                     => 'purchases.purchases.export',
            'api.purchases.attachments.index'          => 'purchases.attachments.view',
            'api.purchases.attachments.store'          => 'purchases.attachments.manage',
            'api.purchases.attachments.destroy'        => 'purchases.attachments.manage',
            'api.purchases.payments.store'             => 'purchases.payments.record',
            'api.purchases.payments.index'             => 'purchases.payments.view',
            'api.purchases.payments.show'              => 'purchases.payments.view',
            'api.purchases.payments.pdf'               => 'purchases.payments.view',
            'api.purchases.payments.attachments.index' => 'purchases.payments.view',
            'api.purchases.payments.attachments.store' => 'purchases.payments.record',
            'api.purchases.payments.attachments.destroy' => 'purchases.payments.record',
            'api.purchases.payments.reverse'           => 'purchases.payments.reverse',

            // Expenses Lite (Doc 10)
            'api.expenses.index'                      => 'expenses.expenses.view',
            'api.expenses.show'                       => 'expenses.expenses.view',
            'api.expenses.pdf'                        => 'expenses.expenses.view',
            'api.expenses.store'                      => 'expenses.expenses.create',
            'api.expenses.defaults'                   => ['expenses.expenses.create', 'expenses.expenses.edit'],
            'api.expenses.calculate'                  => ['expenses.expenses.create', 'expenses.expenses.edit'],
            'api.expenses.update'                     => 'expenses.expenses.edit',
            'api.expenses.record'                     => 'expenses.expenses.record',
            'api.expenses.void'                       => 'expenses.expenses.void',
            'api.expenses.destroy'                    => 'expenses.expenses.delete',
            'api.expenses.export'                     => 'expenses.expenses.export',
            'api.expenses.attachments.index'          => 'expenses.attachments.view',
            'api.expenses.attachments.store'          => 'expenses.attachments.manage',
            'api.expenses.attachments.destroy'        => 'expenses.attachments.manage',
            'api.expenses.payments.store'             => 'expenses.payments.record',
            'api.expenses.payments.index'             => 'expenses.payments.view',
            'api.expenses.payments.show'              => 'expenses.payments.view',
            'api.expenses.payments.pdf'               => 'expenses.payments.view',
            'api.expenses.payments.attachments.index' => 'expenses.payments.view',
            'api.expenses.payments.attachments.store' => 'expenses.payments.record',
            'api.expenses.payments.attachments.destroy' => 'expenses.payments.record',
            'api.expenses.payments.reverse'           => 'expenses.payments.reverse',
            'api.expense-categories.index'            => 'expenses.categories.view',
            'api.expense-categories.store'            => 'expenses.categories.manage',
            'api.expense-categories.update'           => 'expenses.categories.manage',
            'api.expense-categories.destroy'          => 'expenses.categories.manage',
        ];
    }

    /**
     * What $request's route needs: MEMBER, a permission, or a list of which
     * any one will do - null when the route is not here.
     *
     * @return string|list<string>|null
     */
    public static function for(Request $request): string|array|null
    {
        $needs = self::map()[(string) $request->route()?->getName()] ?? null;

        if ($needs instanceof Closure) {
            return $needs($request);
        }

        if (is_array($needs)) {
            return array_values(array_unique(array_map(fn (string $permission) => self::fill($permission, $request), $needs)));
        }

        return $needs === null ? null : self::fill($needs, $request);
    }

    private static function fill(string $permission, Request $request): string
    {
        return strtr($permission, [
            '{kind}' => str_replace('-', '_', (string) $request->route('kind')),
            '{to}'   => Str::plural((string) $request->input('to')),
        ]);
    }
}
