<?php

namespace App\Support;

use App\Models\Admin\Role;

/**
 * The starting admin roles of Doc 15 section 15.3, beside the Super Admin
 * (who may do everything) and the older Admin and Staff. Each opens the
 * admin pages of its area, by route name (PermissionCheckMiddleware: holding
 * a page's create or edit route lets its form save too). They are made once,
 * where missing, and stay editable in Admin > Roles.
 */
final class AdminRoles
{
    /** Every admin's own preferences in the top bar. */
    private const SHARED = ['admin.switch-currency', 'admin.switch-language'];

    /** @return array<string, array{0: string, 1: list<string>}> slug => [name, permissions] */
    public static function templates(): array
    {
        return [
            // Apps Center, package features, application availability, the website's words.
            'product-administrator' => ['Product Administrator', [
                'admin.app-categories.index', 'admin.app-categories.create', 'admin.app-categories.edit', 'admin.app-categories.destroy', 'admin.app-categories.toggle-status',
                'admin.apps.index', 'admin.apps.create', 'admin.apps.edit', 'admin.apps.destroy', 'admin.apps.dependencies.store', 'admin.apps.dependencies.destroy',
                'admin.packages.index', 'admin.package-access.index', 'admin.package-access.update',
                'admin.menu-items.index', 'admin.menu-items.create', 'admin.menu-items.edit', 'admin.menu-items.destroy', 'admin.menu-items.reorder',
                'admin.pages.index', 'admin.pages.create', 'admin.pages.edit', 'admin.pages.destroy',
                'admin.faqs.index', 'admin.faqs.create', 'admin.faqs.edit', 'admin.faqs.destroy',
                'admin.content.index', 'admin.content.update', 'admin.content.items.store', 'admin.content.items.update', 'admin.content.items.destroy', 'admin.content.items.reorder', 'admin.content.upload-image',
                'admin.languages.index', 'admin.languages.create', 'admin.languages.edit', 'admin.languages.destroy', 'admin.languages.translate', 'admin.languages.save-translations',
            ]],
            // Packages and prices, subscriptions, platform invoices, payments and taxes.
            'billing-administrator' => ['Billing Administrator', [
                'admin.packages.index', 'admin.packages.edit',
                'admin.subscription-invoices.index', 'admin.subscription-invoices.show', 'admin.subscription-invoices.pdf', 'admin.subscription-invoices.confirm', 'admin.subscription-invoices.void',
                'admin.payments.index', 'admin.payments.refund', 'admin.refunds.index',
                'admin.subscription-taxes.index', 'admin.subscription-taxes.create', 'admin.subscription-taxes.edit', 'admin.subscription-taxes.destroy',
                'admin.payment-methods.index', 'admin.payment-methods.create', 'admin.payment-methods.edit', 'admin.payment-methods.destroy',
                'admin.currencies.index', 'admin.currencies.create', 'admin.currencies.edit', 'admin.currencies.destroy', 'admin.currencies.save-formatting',
                'admin.clients.index', 'admin.clients.show', 'admin.workspaces.package.review', 'admin.workspaces.package',
                'admin.businesses.index', 'admin.businesses.show',
                'admin.subscriptions.index', 'admin.subscriptions.show', 'admin.subscriptions.plan', 'admin.subscriptions.trial', 'admin.subscriptions.grace',
                'admin.subscriptions.cancel', 'admin.subscriptions.undo', 'admin.subscriptions.resend', 'admin.subscriptions.retry',
            ]],
            // Businesses' and users' support: cases, messages, recovering an account, and read-only support sessions.
            'support-administrator' => ['Support Administrator', [
                'admin.clients.index', 'admin.clients.show',
                'admin.clients.verification', 'admin.clients.recovery', 'admin.clients.sign-out', 'admin.clients.two-factor',
                'admin.businesses.support-session', 'admin.support-sessions.index', 'admin.support-sessions.open', 'admin.support-sessions.end',
                'admin.subscriptions.index', 'admin.subscriptions.show', 'admin.subscriptions.resend',
                'admin.businesses.index', 'admin.businesses.show', 'admin.businesses.notify',
                'admin.tickets.index', 'admin.tickets.show', 'admin.tickets.create', 'admin.tickets.reply', 'admin.tickets.update',
                'admin.departments.index',
                'admin.contact-messages.index', 'admin.contact-messages.show', 'admin.contact-messages.reply',
            ]],
            // Audit records and data access reviews, who may do what, a business's status (restricting, suspending, closing), and locking an account.
            'compliance-and-security-administrator' => ['Compliance and Security Administrator', [
                'admin.audit-log.index', 'admin.audit-log.show', 'admin.audit-log.export',
                'admin.staff.index', 'admin.roles.index',
                'admin.businesses.index', 'admin.businesses.show', 'admin.businesses.status',
                'admin.clients.index', 'admin.clients.show', 'admin.clients.sign-out', 'admin.clients.lock', 'admin.clients.unlock',
                'admin.support-sessions.index',
            ]],
            // Lists and details only: no saving, no exports, and not the settings page, which holds keys.
            'read-only-administrator' => ['Read-Only Administrator', [
                'admin.app-categories.index', 'admin.apps.index', 'admin.audit-log.index', 'admin.audit-log.show',
                'admin.businesses.index', 'admin.businesses.show',
                'admin.clients.index', 'admin.clients.show', 'admin.contact-messages.index', 'admin.contact-messages.show',
                'admin.content.index', 'admin.currencies.index', 'admin.departments.index', 'admin.faqs.index', 'admin.languages.index',
                'admin.menu-items.index', 'admin.package-access.index', 'admin.packages.index', 'admin.pages.index',
                'admin.payment-methods.index', 'admin.roles.index', 'admin.staff.index',
                'admin.subscription-invoices.index', 'admin.subscription-invoices.show', 'admin.subscription-invoices.pdf', 'admin.subscription-taxes.index',
                'admin.payments.index', 'admin.refunds.index',
                'admin.subscriptions.index', 'admin.subscriptions.show', 'admin.support-sessions.index', 'admin.tickets.index', 'admin.tickets.show',
            ]],
        ];
    }

    /** Makes each starting role that is missing. One that exists - perhaps changed since - is left as it is. */
    public static function createMissing(): void
    {
        foreach (self::templates() as $slug => [$name, $permissions]) {
            Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'permissions' => [...$permissions, ...self::SHARED]]);
        }
    }
}
