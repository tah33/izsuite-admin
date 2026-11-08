<?php

namespace Tests\Feature;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Sensitive details (Doc 12 section 14) - Doc 12 Phase D2: an item's
 * purchase price, a payment account's balances and full details, and tax
 * information each show only with their own permission - masked or empty
 * otherwise, in pages, searches, exports, the Dashboard and Reports.
 * Changing what holds them needs them; existing roles keep what they saw.
 */
class SensitiveDetailsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersPurchasesLite;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $this->offerSalesLite();
        $this->offerPurchasesLite();
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);

        // Roles only: with a location, every document would have to name it.
        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->user, 'api');
        $this->setUpSales();
    }

    /** Signs a new member in under a role made with $permissions - the header that picks the business. */
    private function member(string $role, array $permissions): array
    {
        $held   = $this->business->roles()->create(['name' => $role, 'permissions' => Permissions::expand($permissions)]);
        $member = User::factory()->create();

        $this->business->members()->create(['user_id' => $member->id, 'role_id' => $held->id, 'name' => $member->name, 'email' => $member->email]);
        Sanctum::actingAs($member);

        return $this->pick($this->business);
    }

    /** An export's header row. */
    private function exportHeader(string $kind, array $pick = []): array
    {
        return str_getcsv(strtok(ltrim($this->get("/api/v1/{$kind}/export", $pick)->assertOk()->streamedContent(), "\u{FEFF}"), "\n"));
    }

    public function test_an_items_purchase_price_needs_its_permission(): void
    {
        $this->chair->update(['purchase_price' => '90.00']);

        $this->getJson("/api/v1/items/{$this->chair->id}")->assertJsonPath('data.purchase_price', '90.00');
        $this->assertContains('Purchase Price', $this->exportHeader('items'));

        $pick = $this->member('Item Viewer', ['items.view', 'items.export']);
        $this->getJson("/api/v1/items/{$this->chair->id}", $pick)->assertOk()->assertJsonPath('data.purchase_price', null)->assertJsonPath('data.sale_price', '150.00');
        $this->getJson('/api/v1/items', $pick)->assertJsonPath('data.0.purchase_price', null);
        $this->assertNotContains('Purchase Price', $this->exportHeader('items', $pick));

        $pick = $this->member('Buyer', ['items.view', 'items.costs']);
        $this->getJson("/api/v1/items/{$this->chair->id}", $pick)->assertJsonPath('data.purchase_price', '90.00');

        $this->assertContains('items.costs', Permissions::expand(['items.edit']));
    }

    public function test_balances_and_full_account_details_need_their_permissions(): void
    {
        $account = $this->account(['account_number' => '0012345678', 'opening_balance' => '500.00', 'opening_balance_date' => '2026-10-01']);
        $this->pay($this->issuedInvoice(), $account, '100')->assertCreated();

        $this->getJson("/api/v1/payment-accounts/{$account->id}")->assertJsonPath('data.balance', '600.00')->assertJsonPath('data.account_number', '0012345678');

        $pick  = $this->member('Account Viewer', ['dashboard.view', 'reports.view', 'payment_accounts.view']);
        $shown = $this->getJson("/api/v1/payment-accounts/{$account->id}", $pick)->assertOk()->json('data');
        $this->assertSame([null, null, '•••• 5678'], [$shown['balance'], $shown['opening_balance'], $shown['account_number']]);
        $this->getJson("/api/v1/payment-accounts/{$account->id}/transactions", $pick)->assertJsonPath('data.0.running_balance', null)->assertJsonPath('data.0.amount', '100.00');
        $this->getJson('/api/v1/dashboard', $pick)->assertOk()->assertJsonPath('data.accounts', null);
        $this->assertNotContains('account-transactions', array_column($this->getJson('/api/v1/reports', $pick)->json('data'), 'key'));

        $pick = $this->member('Accountant', ['reports.view', 'payment_accounts.balances', 'payment_accounts.details']);
        $this->getJson("/api/v1/payment-accounts/{$account->id}", $pick)->assertJsonPath('data.balance', '600.00')->assertJsonPath('data.account_number', '0012345678');
        $this->assertContains('account-transactions', array_column($this->getJson('/api/v1/reports', $pick)->json('data'), 'key'));

        $this->assertEmpty(array_diff(['payment_accounts.balances', 'payment_accounts.details'], Permissions::expand(['payment_accounts.edit'])));
        $this->assertContains('payment_accounts.balances', Permissions::expand(['payment_accounts.transfer']));
    }

    public function test_tax_information_needs_its_permission(): void
    {
        $this->customer->update(['tax_number' => 'BIN-778899']);
        $invoice = $this->issuedInvoice();

        $this->assertCount(1, $this->getJson('/api/v1/contacts?search=BIN-778899')->json('data'));
        $this->assertContains('Tax ID', $this->exportHeader('contacts'));

        $pick = $this->member('Contact Viewer', ['contacts.view', 'contacts.export', 'sales.invoices.view']);
        $this->getJson("/api/v1/contacts/{$this->customer->id}", $pick)->assertOk()->assertJsonPath('data.tax_number', null)->assertJsonPath('data.name', 'Uddin Traders');
        $this->assertCount(0, $this->getJson('/api/v1/contacts?search=BIN-778899', $pick)->json('data'));
        $this->assertNotContains('Tax ID', $this->exportHeader('contacts', $pick));
        $this->getJson("/api/v1/sales/invoices/{$invoice}", $pick)->assertJsonPath('data.customer.tax_number', null)->assertJsonPath('data.customer.name', 'Uddin Traders');

        // The tax reports need it on top of the apps' values.
        $parts = ['reports.view', 'sales.invoices.view', 'sales.values.view', 'purchases.purchases.view', 'purchases.values.view'];
        $pick  = $this->member('Analyst', $parts);
        $this->assertNotContains('tax-summary', array_column($this->getJson('/api/v1/reports', $pick)->json('data'), 'key'));
        $pick = $this->member('Tax Analyst', [...$parts, 'taxes.view']);
        $this->assertContains('tax-summary', array_column($this->getJson('/api/v1/reports', $pick)->json('data'), 'key'));

        $this->assertContains('taxes.view', Permissions::expand(['contacts.edit']));
    }

    public function test_existing_roles_keep_what_they_saw(): void
    {
        $role    = $this->business->roles()->create(['name' => 'Old Desk', 'permissions' => ['items.view', 'payment_accounts.view', 'contacts.view']]);
        $auditor = $this->business->roles()->create(['name' => 'Old Auditor', 'permissions' => ['settings.audit']]);
        $other   = $this->business->roles()->create(['name' => 'Old Glance', 'permissions' => ['dashboard.view']]);
        $before  = $this->business->fresh()->nav_version;

        (require database_path('migrations/2025_01_01_000107_add_sensitive_permissions_to_roles.php'))->up();

        $sensitive = ['items.costs', 'payment_accounts.balances', 'payment_accounts.details', 'taxes.view'];
        $this->assertSame(['items.view', 'payment_accounts.view', 'contacts.view', ...$sensitive], $role->fresh()->permissions);
        $this->assertSame(['settings.audit', ...$sensitive], $auditor->fresh()->permissions);
        $this->assertSame(['dashboard.view'], $other->fresh()->permissions);
        $this->assertGreaterThan($before, $this->business->fresh()->nav_version);
    }

    public function test_the_activity_log_needs_everything_it_records(): void
    {
        // It writes down amounts, prices, balances, account numbers and tax details as they were changed.
        $this->assertEmpty(array_diff(
            ['items.costs', 'payment_accounts.balances', 'payment_accounts.details', 'taxes.view', 'sales.values.view', 'purchases.values.view', 'expenses.values.view'],
            Permissions::expand(['settings.audit']),
        ));

        $role = $this->postJson('/api/v1/roles', ['name' => 'Auditor', 'permissions' => ['settings.audit']], $this->pick($this->business))->assertCreated()->json('data');
        $this->assertContains('sales.values.view', $role['permissions']);
        $this->assertContains('taxes.view', $role['permissions']);
    }
}
