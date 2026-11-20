<?php

namespace App\Support;

use App\Enums\DocumentType;
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
            // Any member: each kind of result is kept to what their role may view (GlobalSearchService).
            'api.search'                   => self::MEMBER,
            'api.files.link'               => self::MEMBER,
            'api.files.limits'             => self::MEMBER,
            'api.business-settings.usage'  => self::MEMBER,
            // Each user's own notifications (Doc 14 section 15).
            'api.notifications.index'      => self::MEMBER,
            'api.notifications.unread'     => self::MEMBER,
            'api.notifications.read'       => self::MEMBER,
            'api.notifications.read-all'   => self::MEMBER,
            // izSuite's announcements to the business's people (Doc 15 section 15.21).
            'api.announcements.index'      => self::MEMBER,
            'api.announcements.dismiss'    => self::MEMBER,
            // Support cases (Doc 15 section 15.22): anyone in the business asks izSuite; the owner sees every case.
            'api.support-cases.index'      => self::MEMBER,
            'api.support-cases.store'      => self::MEMBER,
            'api.support-cases.show'       => self::MEMBER,
            'api.support-cases.reply'      => self::MEMBER,

            // The subscription, its invoices and its billing details (Doc 13 sections 11 and 21); over its limits, only the owner chooses (OverLimitService)
            'api.subscription.show'               => 'settings.subscription',
            'api.subscription.subscribe'          => 'settings.subscription',
            'api.subscription.quote'              => 'settings.subscription',
            'api.subscription.withdraw'           => 'settings.subscription',
            'api.subscription.cancel'             => 'settings.subscription',
            'api.subscription.resume'             => 'settings.subscription',
            'api.subscription.change.preview'     => 'settings.subscription',
            'api.subscription.change'             => 'settings.subscription',
            'api.subscription.change.undo'        => 'settings.subscription',
            'api.subscription.billing-details'    => 'settings.subscription',
            'api.subscription.over-limit'         => 'settings.subscription',
            'api.subscription.keep'               => 'settings.subscription',
            'api.subscription.invoices'           => 'settings.subscription',
            'api.subscription.invoices.reference' => 'settings.subscription',
            'api.subscription.invoices.pdf'       => 'settings.subscription',
            'api.subscription.card.checkout'      => 'settings.subscription',
            'api.subscription.card.complete'      => 'settings.subscription',
            'api.subscription.card.charge'        => 'settings.subscription',
            'api.subscription.card.setup'         => 'settings.subscription',
            'api.subscription.card.remove'        => 'settings.subscription',

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
            // Merging another contact into one (Doc 17 Phase C3).
            'api.contacts.merge.candidates'    => 'contacts.merge',
            'api.contacts.merge.preview'       => 'contacts.merge',
            'api.contacts.merge'               => 'contacts.merge',

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
            // Doc 18 Phase H1: what POS's reports can be narrowed to.
            'api.reports.pos-choices' => 'pos.reports.view',

            // Settings (Doc 11): what forms pick from is open to every member.
            'api.business-settings.show'                       => self::MEMBER,
            'api.business-settings.save'                       => 'settings.business',
            'api.business-settings.regional.show'              => 'settings.view',
            'api.business-settings.regional.update'            => 'settings.business',
            'api.business-settings.items.show'                 => self::MEMBER,
            'api.business-settings.items.update'               => 'settings.business',
            'api.business-settings.email.show'                 => 'settings.view',
            'api.business-settings.email.update'               => 'settings.business',
            'api.business-settings.notifications.show'         => 'settings.view',
            'api.business-settings.notifications.update'       => 'settings.business',
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
            // Sales teams, the full Sales app's and CRM's (Doc 17 Phase A2): either app's settings change them.
            'api.business-settings.sales-teams.index'          => 'settings.view',
            'api.business-settings.sales-teams.people'         => 'settings.view',
            'api.business-settings.sales-teams.store'          => ['sales.settings.manage', 'crm.settings.manage'],
            'api.business-settings.sales-teams.update'         => ['sales.settings.manage', 'crm.settings.manage'],
            'api.business-settings.sales-teams.destroy'        => ['sales.settings.manage', 'crm.settings.manage'],
            'api.business-settings.purchases.show'             => 'settings.view',
            'api.business-settings.purchases.update'           => 'purchases.settings.manage',
            // Settings > CRM (Doc 17 Phase A1).
            // Doc 18 Phase A1: Settings → Point of Sale.
            'api.business-settings.pos.show'                   => 'pos.settings.manage',
            'api.business-settings.pos.update'                 => 'pos.settings.manage',
            'api.business-settings.pos.cash-count.update'      => 'pos.settings.manage',
            'api.business-settings.pos.selling.update'         => 'pos.settings.manage',
            'api.business-settings.pos.payment-methods.store'  => 'pos.settings.manage',
            'api.business-settings.pos.payment-methods.update' => 'pos.settings.manage',
            'api.business-settings.pos.payment-methods.destroy' => 'pos.settings.manage',
            // Doc 19 Phase A1: Settings → eCommerce.
            'api.business-settings.ecommerce.show'             => 'ecommerce.settings.manage',
            'api.business-settings.ecommerce.update'           => 'ecommerce.settings.manage',
            'api.business-settings.crm.show'                   => 'settings.view',
            'api.business-settings.crm.update'                 => 'crm.settings.manage',
            'api.business-settings.crm.options.store'          => 'crm.settings.manage',
            'api.business-settings.crm.options.update'         => 'crm.settings.manage',
            'api.business-settings.crm.options.destroy'        => 'crm.settings.manage',
            'api.business-settings.crm.pipelines.update'       => 'crm.settings.manage',
            'api.business-settings.crm.pipelines.store'        => 'crm.settings.manage',
            'api.business-settings.crm.pipelines.destroy'      => 'crm.settings.manage',
            // Phase F3b: its e-mail templates.
            'api.business-settings.crm.email-templates.store'   => 'crm.settings.manage',
            'api.business-settings.crm.email-templates.update'  => 'crm.settings.manage',
            'api.business-settings.crm.email-templates.destroy' => 'crm.settings.manage',
            // Phase I1: the lead assignment rules, with their own permission (17.30).
            'api.business-settings.crm.assignment-rules.index'   => 'crm.rules.manage',
            'api.business-settings.crm.assignment-rules.store'   => 'crm.rules.manage',
            'api.business-settings.crm.assignment-rules.order'   => 'crm.rules.manage',
            'api.business-settings.crm.assignment-rules.update'  => 'crm.rules.manage',
            'api.business-settings.crm.assignment-rules.destroy' => 'crm.rules.manage',
            // Phase I2: the lead scoring rules, under the same permission.
            'api.business-settings.crm.scoring-rules.index'      => 'crm.rules.manage',
            'api.business-settings.crm.scoring-rules.store'      => 'crm.rules.manage',
            'api.business-settings.crm.scoring-rules.update'     => 'crm.rules.manage',
            'api.business-settings.crm.scoring-rules.destroy'    => 'crm.rules.manage',
            // Phase I4: the custom fields, as Settings → CRM's lists.
            'api.business-settings.crm.custom-fields.index'      => 'crm.settings.manage',
            'api.business-settings.crm.custom-fields.store'      => 'crm.settings.manage',
            'api.business-settings.crm.custom-fields.order'      => 'crm.settings.manage',
            'api.business-settings.crm.custom-fields.update'     => 'crm.settings.manage',
            'api.business-settings.crm.custom-fields.destroy'    => 'crm.settings.manage',
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
            'api.apps-center.show'   => 'settings.apps',
            'api.apps.deactivation'  => 'settings.apps',
            'api.apps.activate'      => 'settings.apps',
            'api.apps.deactivate'    => 'settings.apps',
            'api.activity-log.index' => 'settings.audit',
            'api.activity-log.users' => 'settings.audit',

            // Sales Lite (Doc 08): quotations, proforma invoices, invoices and credit notes by their kind.
            'api.sales.overview'            => 'sales.values.view',
            // A customer's credit control and exposure (Doc 16 Phase G1): seen with the values, set with its permission.
            'api.sales.customers.credit'        => 'sales.values.view',
            'api.sales.customers.credit.update' => 'sales.credit.manage',
            // The sales form, the Overview's filters and Settings → Sales teams choose from it.
            'api.sales.salespeople'         => [...self::SALES_DRAFTS, 'sales.values.view', 'sales.settings.manage'],
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
            // A sales order's (Doc 16 Phase B): confirming, holding, resuming and completing come under Confirm.
            'api.sales.confirm'             => 'sales.{kind}.confirm',
            'api.sales.hold'                => 'sales.{kind}.confirm',
            'api.sales.resume'              => 'sales.{kind}.confirm',
            'api.sales.complete'            => 'sales.{kind}.confirm',
            'api.sales.amend'               => 'sales.{kind}.amend',
            'api.sales.duplicate'           => 'sales.{kind}.create',
            'api.sales.invoice'             => 'sales.invoices.create',
            'api.sales.deposit'             => 'sales.invoices.create',
            // Deliveries (Doc 16 Phase C): Ready and Delivered come under Mark Ready or Delivered.
            'api.sales.deliveries.index'               => 'sales.deliveries.view',
            'api.sales.deliveries.show'                => 'sales.deliveries.view',
            'api.sales.deliveries.pdf'                 => 'sales.deliveries.view',
            'api.sales.deliveries.attachments.index'   => 'sales.deliveries.view',
            'api.sales.deliveries.store'               => 'sales.deliveries.create',
            'api.sales.deliveries.update'              => 'sales.deliveries.edit',
            'api.sales.deliveries.attachments.store'   => 'sales.deliveries.edit',
            'api.sales.deliveries.attachments.destroy' => 'sales.deliveries.edit',
            'api.sales.deliveries.ready'               => 'sales.deliveries.deliver',
            'api.sales.deliveries.deliver'             => 'sales.deliveries.deliver',
            'api.sales.deliveries.cancel'              => 'sales.deliveries.cancel',
            'api.sales.deliveries.destroy'             => 'sales.deliveries.delete',
            // Price lists (Doc 16 Phase F1): what a form's lines cost by them is for whoever fills one in.
            'api.sales.price-lists.index'           => 'sales.price_lists.view',
            'api.sales.price-lists.show'            => 'sales.price_lists.view',
            'api.sales.price-lists.store'           => 'sales.price_lists.manage',
            'api.sales.price-lists.update'          => 'sales.price_lists.manage',
            'api.sales.price-lists.destroy'         => 'sales.price_lists.manage',
            'api.sales.prices'                      => self::SALES_DRAFTS,
            // Customer returns (Doc 16 Phase E): Received and Completed come under Mark Received or Completed.
            'api.sales.returns.index'               => 'sales.returns.view',
            'api.sales.returns.show'                => 'sales.returns.view',
            'api.sales.returns.attachments.index'   => 'sales.returns.view',
            'api.sales.returns.store'               => 'sales.returns.create',
            'api.sales.returns.update'              => 'sales.returns.edit',
            'api.sales.returns.submit'              => 'sales.returns.edit',
            'api.sales.returns.attachments.store'   => 'sales.returns.edit',
            'api.sales.returns.attachments.destroy' => 'sales.returns.edit',
            'api.sales.returns.authorise'           => 'sales.returns.authorise',
            'api.sales.returns.reject'              => 'sales.returns.authorise',
            'api.sales.returns.receive'             => 'sales.returns.receive',
            'api.sales.returns.complete'            => 'sales.returns.receive',
            'api.sales.returns.cancel'              => 'sales.returns.cancel',
            'api.sales.returns.destroy'             => 'sales.returns.delete',
            // CRM's leads (Doc 17 Phase B1): which ones a user sees is LeadVisibility's.
            'api.crm.leads.index'     => 'crm.leads.view',
            'api.crm.leads.options'   => 'crm.leads.view',
            'api.crm.leads.show'      => 'crm.leads.view',
            'api.crm.leads.store'     => 'crm.leads.manage',
            'api.crm.leads.update'    => 'crm.leads.manage',
            'api.crm.leads.contact'   => 'crm.leads.manage',
            'api.crm.leads.qualify'   => 'crm.leads.manage',
            'api.crm.leads.unqualify' => 'crm.leads.manage',
            'api.crm.leads.archive'   => 'crm.leads.manage',
            'api.crm.leads.assign'    => 'crm.leads.assign',
            'api.crm.leads.assign-by-rules' => 'crm.leads.assign',
            'api.crm.leads.reopen'    => 'crm.leads.reopen',
            // Converting one (Phase C1); a new contact needs contacts.create besides (CrmLeadConversion).
            'api.crm.leads.conversion' => 'crm.leads.convert',
            'api.crm.leads.convert'    => 'crm.leads.convert',
            // Its possible duplicates (Phase C2): seen by whoever sees it, decided with Create and Edit.
            'api.crm.leads.duplicates.index'  => 'crm.leads.view',
            'api.crm.leads.duplicates.ignore' => 'crm.leads.manage',
            'api.crm.leads.duplicates.link'   => 'crm.leads.manage',
            'api.crm.leads.duplicates.clear'  => 'crm.leads.manage',
            // Its files, comments and timeline (Phase B2b): whoever sees it reads and comments; files are changed with Create and Edit.
            'api.crm.leads.attachments.index'   => 'crm.leads.view',
            'api.crm.leads.attachments.store'   => 'crm.leads.manage',
            'api.crm.leads.attachments.destroy' => 'crm.leads.manage',
            // Phase I3: its marketing consent, with the lead.
            'api.crm.leads.consents.index'      => 'crm.leads.view',
            'api.crm.leads.consents.store'      => 'crm.leads.manage',
            'api.crm.leads.consents.withdraw'   => 'crm.leads.manage',
            'api.crm.leads.activity'            => 'crm.leads.view',
            'api.crm.leads.comments.index'      => 'crm.leads.view',
            'api.crm.leads.comments.people'     => 'crm.leads.view',
            'api.crm.leads.comments.store'      => 'crm.leads.view',
            'api.crm.leads.comments.destroy'    => 'crm.leads.view',
            // Opportunities (Phase D1): who sees which is OpportunityVisibility's; moving one between stages is its own.
            // Phase G1: the CRM Overview, and (Phase G2) the Forecast, for whoever may view opportunities.
            'api.crm.overview'              => 'crm.opportunities.view',
            'api.crm.forecast'              => 'crm.opportunities.view',
            'api.crm.opportunities.index'   => 'crm.opportunities.view',
            'api.crm.opportunities.options' => 'crm.opportunities.view',
            'api.crm.opportunities.board'   => 'crm.opportunities.view',
            'api.crm.opportunities.calendar' => 'crm.opportunities.view',
            // Phase E1: activities.
            'api.crm.activities.index'     => 'crm.activities.view',
            'api.crm.activities.options'   => 'crm.activities.view',
            'api.crm.activities.for'       => 'crm.activities.view',
            'api.crm.activities.regarding' => 'crm.activities.manage',
            'api.crm.activities.store'     => 'crm.activities.manage',
            'api.crm.activities.update'    => 'crm.activities.manage',
            'api.crm.activities.complete'  => 'crm.activities.manage',
            'api.crm.activities.cancel'    => 'crm.activities.manage',
            // Phase F4: a contact's CRM part, for whoever sees contacts - its leads and opportunities as they may see them (CrmContactSummaryService).
            'api.crm.contacts.summary' => 'contacts.view',
            // Phase I3: a contact's marketing consent, with the contact.
            'api.crm.contacts.consents.index'    => 'contacts.view',
            'api.crm.contacts.consents.store'    => 'contacts.edit',
            'api.crm.contacts.consents.withdraw' => 'contacts.edit',
            // Doc 19 Phase A1: eCommerce's storefronts.
            'api.ecommerce.storefronts.index'   => 'ecommerce.store.view',
            'api.ecommerce.storefronts.options' => 'ecommerce.store.manage',
            'api.ecommerce.storefronts.store'   => 'ecommerce.store.manage',
            'api.ecommerce.storefronts.update'  => 'ecommerce.store.manage',
            'api.ecommerce.storefronts.destroy' => 'ecommerce.store.manage',
            // Phase A3: their domains.
            'api.ecommerce.domains.index'       => 'ecommerce.store.view',
            'api.ecommerce.domains.store'       => 'ecommerce.domains.manage',
            'api.ecommerce.domains.verify'      => 'ecommerce.domains.manage',
            'api.ecommerce.domains.primary'     => 'ecommerce.domains.manage',
            'api.ecommerce.domains.destroy'     => 'ecommerce.domains.manage',
            // Phase C4a: a storefront's theme.
            'api.ecommerce.themes.show'         => 'ecommerce.themes.manage',
            'api.ecommerce.themes.update'       => 'ecommerce.themes.manage',
            'api.ecommerce.themes.pictures'     => 'ecommerce.themes.manage',
            'api.ecommerce.themes.publish'      => 'ecommerce.themes.manage',
            'api.ecommerce.themes.discard'      => 'ecommerce.themes.manage',
            // Phase C5a: a storefront's pages.
            'api.ecommerce.pages.index'         => 'ecommerce.themes.manage',
            'api.ecommerce.pages.show'          => 'ecommerce.themes.manage',
            'api.ecommerce.pages.update'        => 'ecommerce.themes.manage',
            'api.ecommerce.pages.store'         => 'ecommerce.themes.manage',
            'api.ecommerce.pages.destroy'       => 'ecommerce.themes.manage',
            // Phase B1: the catalog.
            'api.ecommerce.catalog.index'           => 'ecommerce.catalog.view',
            'api.ecommerce.catalog.show'            => 'ecommerce.catalog.view',
            'api.ecommerce.catalog.update'          => 'ecommerce.catalog.publish',
            'api.ecommerce.catalog.destroy'         => 'ecommerce.catalog.publish',
            'api.ecommerce.catalog.pictures.store'  => 'ecommerce.catalog.publish',
            'api.ecommerce.catalog.pictures.destroy' => 'ecommerce.catalog.publish',
            'api.ecommerce.catalog.variants.items'  => 'ecommerce.catalog.publish',
            'api.ecommerce.catalog.variants.update' => 'ecommerce.catalog.publish',
            // Phase B2: categories and collections.
            'api.ecommerce.categories.index'          => 'ecommerce.catalog.view',
            'api.ecommerce.categories.update'         => 'ecommerce.catalog.manage',
            'api.ecommerce.categories.image.store'    => 'ecommerce.catalog.manage',
            'api.ecommerce.categories.image.destroy'  => 'ecommerce.catalog.manage',
            'api.ecommerce.collections.index'         => 'ecommerce.catalog.view',
            'api.ecommerce.collections.options'       => 'ecommerce.catalog.manage',
            'api.ecommerce.collections.store'         => 'ecommerce.catalog.manage',
            'api.ecommerce.collections.update'        => 'ecommerce.catalog.manage',
            'api.ecommerce.collections.destroy'       => 'ecommerce.catalog.manage',
            'api.ecommerce.collections.image.store'   => 'ecommerce.catalog.manage',
            'api.ecommerce.collections.image.destroy' => 'ecommerce.catalog.manage',
            // Doc 18 Phase A2: POS registers.
            'api.pos.registers.index'   => 'pos.registers.view',
            'api.pos.registers.options' => 'pos.registers.manage',
            'api.pos.registers.store'   => 'pos.registers.manage',
            'api.pos.registers.update'  => 'pos.registers.manage',
            'api.pos.registers.destroy' => 'pos.registers.manage',
            // Phase I3: a register's devices.
            'api.pos.devices.index'     => 'pos.registers.view',
            'api.pos.devices.store'     => 'pos.registers.manage',
            'api.pos.devices.update'    => 'pos.registers.manage',
            'api.pos.devices.destroy'   => 'pos.registers.manage',
            'api.pos.devices.test'      => 'pos.registers.manage',
            'api.pos.devices.test-page' => 'pos.registers.manage',
            // Doc 18 Phase B1: POS sessions.
            'api.pos.sessions.index'   => 'pos.sessions.view',
            'api.pos.sessions.options' => 'pos.sessions.view',
            'api.pos.sessions.show'    => 'pos.sessions.view',
            'api.pos.sessions.store'   => 'pos.sessions.open',
            'api.pos.sessions.close'   => ['pos.sessions.open', 'pos.sessions.approve'],
            'api.pos.sessions.approve' => 'pos.sessions.approve',
            'api.pos.sessions.reopen'  => 'pos.sessions.reopen',
            // Doc 18 Phase C1: the selling screen, in the user's own open session (PosSellingService).
            'api.pos.sell.show'  => 'pos.selling.open',
            // Phase I1: one's PIN, and one's register's lock.
            'api.pos.lock'   => 'pos.selling.open',
            'api.pos.unlock' => 'pos.selling.open',
            'api.pos.pin'    => 'pos.selling.open',
            // Phase I2: a manager's approval at one's register.
            'api.pos.approvers'       => 'pos.selling.open',
            'api.pos.approvals.store' => 'pos.selling.open',
            'api.pos.sell.items' => 'pos.selling.open',
            'api.pos.sell.quote' => 'pos.selling.open',
            // Phase D3b: what credit the customer holds, to pay a sale with.
            'api.pos.sell.credit' => 'pos.selling.sell',
            // Phase C2: a sale paid and completed.
            'api.pos.orders.store' => 'pos.selling.sell',
            // Phase G1: POS's pages of records; G2, its Overview.
            'api.pos.overview'             => 'pos.orders.view',
            'api.pos.orders.index'         => 'pos.orders.view',
            'api.pos.returns.index'        => 'pos.returns.view',
            'api.pos.cash-movements.index' => 'pos.cash.view',
            // Phase C4: its receipt - the first copy its cashier's, every later one needing Reprint Receipts (PosReceiptService).
            'api.pos.orders.receipt'       => ['pos.selling.sell', 'pos.receipts.reprint'],
            'api.pos.orders.receipt.email' => ['pos.selling.sell', 'pos.receipts.reprint'],
            // Phase D4: its formal invoice, as its receipt (PosFormalInvoices).
            'api.pos.orders.formal-invoice.store' => ['pos.selling.sell', 'pos.receipts.reprint'],
            'api.pos.orders.formal-invoice.show'  => ['pos.selling.sell', 'pos.receipts.reprint'],
            // Phase D1: orders held at the user's own register (PosHoldService).
            'api.pos.held-orders.index'  => 'pos.selling.hold',
            'api.pos.held-orders.store'  => 'pos.selling.hold',
            'api.pos.held-orders.show'   => 'pos.selling.hold',
            'api.pos.held-orders.resume' => 'pos.selling.hold',
            'api.pos.held-orders.cancel' => 'pos.selling.cancel',
            // Phase E1: returns, in one's own session; paying one back needs Process Refunds too (PosReturnService).
            'api.pos.returns.lookup' => 'pos.returns.process',
            'api.pos.returns.quote'  => 'pos.returns.process',
            'api.pos.returns.store'  => 'pos.returns.process',
            // Phase E2: a return's refund receipt; refunds waiting to be confirmed, as pending payments are.
            'api.pos.returns.receipt'          => 'pos.returns.process',
            'api.pos.sessions.pending-refunds' => 'pos.payments.confirm',
            'api.pos.returns.refund.confirm'   => 'pos.payments.confirm',
            'api.pos.returns.refund.decline'   => 'pos.payments.confirm',
            // Phase F1: cash movements.
            'api.pos.cash-movements.options'  => 'pos.cash.move',
            'api.pos.cash-movements.store'    => 'pos.cash.move',
            'api.pos.sessions.cash-movements' => 'pos.sessions.view',
            // Phase F3: a session's X and Z reports.
            'api.pos.sessions.report'         => 'pos.sessions.view',
            'api.pos.cash-movements.approve'  => 'pos.cash.approve',
            'api.pos.cash-movements.decline'  => 'pos.cash.approve',
            // Phase D2: payments waiting to be confirmed (PosPendingPayments).
            'api.pos.sessions.pending-payments' => 'pos.payments.confirm',
            'api.pos.order-payments.confirm'    => 'pos.payments.confirm',
            'api.pos.order-payments.decline'    => 'pos.payments.confirm',
            // Phase F3a: e-mail from a lead, an opportunity or a contact - the record's own view permission checked too (CrmEmailService).
            'api.crm.emails.draft' => 'crm.emails.send',
            'api.crm.emails.store' => 'crm.emails.send',
            // Phase E3: a record's timeline, for whoever sees the record - its activities only with View Activities (CrmTimelineService).
            'api.crm.timeline' => fn (Request $request) => match ((string) $request->route('kind')) {
                'lead'        => 'crm.leads.view',
                'opportunity' => 'crm.opportunities.view',
                default       => 'contacts.view',
            },
            'api.crm.opportunities.show'    => 'crm.opportunities.view',
            'api.crm.opportunities.store'   => 'crm.opportunities.manage',
            'api.crm.opportunities.update'  => 'crm.opportunities.manage',
            'api.crm.opportunities.stage'   => 'crm.opportunities.stage',
            // How it ends (Phase D2a): won or lost, reopened, archived and restored.
            'api.crm.opportunities.win'     => 'crm.opportunities.close',
            'api.crm.opportunities.lose'    => 'crm.opportunities.close',
            'api.crm.opportunities.reopen'  => 'crm.opportunities.reopen',
            'api.crm.opportunities.archive' => 'crm.opportunities.manage',
            'api.crm.opportunities.restore' => 'crm.opportunities.manage',
            // Its files, comments and timeline (Phase D2b): whoever sees it reads and comments; files are changed with Create and Edit.
            'api.crm.opportunities.attachments.index'   => 'crm.opportunities.view',
            'api.crm.opportunities.attachments.store'   => 'crm.opportunities.manage',
            'api.crm.opportunities.attachments.destroy' => 'crm.opportunities.manage',
            'api.crm.opportunities.activity'            => 'crm.opportunities.view',
            // Phase F1: the sales documents made from it, those of the kinds the user views.
            'api.crm.opportunities.documents'           => 'crm.opportunities.view',
            'api.crm.opportunities.comments.index'      => 'crm.opportunities.view',
            'api.crm.opportunities.comments.people'     => 'crm.opportunities.view',
            'api.crm.opportunities.comments.store'      => 'crm.opportunities.view',
            'api.crm.opportunities.comments.destroy'    => 'crm.opportunities.view',
            // Customer advances and credit applied to invoices (Doc 16 Phase D1).
            'api.sales.advances.index'                 => 'sales.advances.view',
            'api.sales.advances.show'                  => 'sales.advances.view',
            'api.sales.advances.store'                 => 'sales.advances.record',
            'api.sales.advances.reverse'               => 'sales.advances.reverse',
            'api.sales.advances.invoices'              => 'sales.advances.apply',
            'api.sales.allocations.sources'            => 'sales.advances.apply',
            'api.sales.allocations.store'              => 'sales.advances.apply',
            'api.sales.allocations.note-invoices'      => 'sales.advances.apply',
            'api.sales.allocations.remove'             => 'sales.advances.apply',
            // Comments and the activity timeline (Doc 14 Phase D): whoever may view it. Only its author deletes a comment (RecordCommentService).
            'api.sales.activity'            => 'sales.{kind}.view',
            'api.sales.comments.index'      => 'sales.{kind}.view',
            'api.sales.comments.people'     => 'sales.{kind}.view',
            'api.sales.comments.store'      => 'sales.{kind}.view',
            'api.sales.comments.destroy'    => 'sales.{kind}.view',

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
            'api.purchases.activity'                   => 'purchases.purchases.view',
            'api.purchases.comments.index'             => 'purchases.purchases.view',
            'api.purchases.comments.people'            => 'purchases.purchases.view',
            'api.purchases.comments.store'             => 'purchases.purchases.view',
            'api.purchases.comments.destroy'           => 'purchases.purchases.view',
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
            'api.expenses.activity'                   => 'expenses.expenses.view',
            'api.expenses.comments.index'             => 'expenses.expenses.view',
            'api.expenses.comments.people'            => 'expenses.expenses.view',
            'api.expenses.comments.store'             => 'expenses.expenses.view',
            'api.expenses.comments.destroy'           => 'expenses.expenses.view',
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
        $to = DocumentType::tryFrom((string) $request->input('to'));

        return strtr($permission, [
            '{kind}' => str_replace('-', '_', (string) $request->route('kind')),
            // A sales order's kind is "orders": sales.orders.create.
            '{to}'   => $to ? str_replace('-', '_', $to->kind()) : Str::plural((string) $request->input('to')),
        ]);
    }
}
