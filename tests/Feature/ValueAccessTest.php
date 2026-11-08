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
 * An app's values (Doc 12 section 14) - Doc 12 Phase D1: a role without an
 * app's Values sees its records with their amounts empty, finds none by
 * amount, has no PDFs of them, and no Dashboard part or report built on
 * them. Making or changing a record needs its values; existing roles keep
 * what they saw.
 */
class ValueAccessTest extends TestCase
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

    public function test_a_role_without_values_sees_records_without_amounts(): void
    {
        $id      = $this->issuedInvoice();
        $payment = $this->pay($id, $this->account(), '100')->assertCreated()->json('data.id');

        $this->assertSame('335.50', $this->getJson("/api/v1/sales/invoices/{$id}")->json('data.grand_total'));
        $this->assertCount(1, $this->getJson('/api/v1/documents?search=335.50')->json('data'));

        $pick    = $this->member('Invoice Viewer', ['sales.invoices.view', 'sales.payments.view', 'documents.view']);
        $invoice = $this->getJson("/api/v1/sales/invoices/{$id}", $pick)->assertOk()->json('data');

        $this->assertSame('INV-00001', $invoice['number']);
        $this->assertSame([null, null, null, []], [$invoice['grand_total'], $invoice['balance_due'], $invoice['amount_paid'], $invoice['taxes']]);
        $this->assertSame([null, null, 'Office Chair'], [$invoice['lines'][0]['unit_price'], $invoice['lines'][0]['amount'], $invoice['lines'][0]['name']]);
        $this->assertNull($invoice['payments'][0]['amount']);

        $this->getJson('/api/v1/sales/invoices', $pick)->assertJsonPath('data.0.grand_total', null)->assertJsonPath('data.0.number', 'INV-00001');
        $this->getJson("/api/v1/sales/payments/{$payment}", $pick)->assertOk()->assertJsonPath('data.amount', null)->assertJsonPath('data.balance_after', null);
        $this->getJson('/api/v1/documents', $pick)->assertJsonPath('data.0.grand_total', null);

        // Nor found by its amount, nor seen as a PDF.
        $this->assertCount(0, $this->getJson('/api/v1/documents?search=335.50', $pick)->json('data'));
        $this->getJson("/api/v1/sales/invoices/{$id}/pdf", $pick)->assertForbidden();

        $pick = $this->member('Invoice Reader', ['sales.invoices.view', 'sales.values.view']);
        $this->getJson("/api/v1/sales/invoices/{$id}", $pick)->assertJsonPath('data.grand_total', '335.50');
    }

    public function test_more_than_viewing_a_record_needs_its_values(): void
    {
        $this->assertNotContains('sales.values.view', Permissions::expand(['sales.invoices.view']));
        $this->assertContains('sales.values.view', Permissions::expand(['sales.invoices.create']));
        $this->assertContains('sales.values.view', Permissions::expand(['sales.quotations.send']));
        $this->assertContains('sales.values.view', Permissions::expand(['sales.refunds.record']));
        $this->assertContains('purchases.values.view', Permissions::expand(['purchases.payments.record']));
        $this->assertContains('expenses.values.view', Permissions::expand(['expenses.expenses.export']));
        $this->assertNotContains('expenses.values.view', Permissions::expand(['expenses.categories.manage']));

        // The templates: each its own app's values, View Only every app's.
        $this->assertContains('sales.values.view', Permissions::forTemplate('sales_user', ['sales', 'purchases']));
        $this->assertNotContains('purchases.values.view', Permissions::forTemplate('sales_user', ['sales', 'purchases']));
        $this->assertEmpty(array_diff(['sales.values.view', 'purchases.values.view', 'expenses.values.view'], Permissions::forTemplate('view_only', ['sales', 'purchases', 'expenses'])));
    }

    public function test_the_dashboard_and_reports_follow_values(): void
    {
        $this->issuedInvoice();

        $pick  = $this->member('Glance', ['dashboard.view', 'sales.invoices.view', 'reports.view']);
        $board = $this->getJson('/api/v1/dashboard', $pick)->assertOk()->json('data');
        $this->assertNull($board['sales']);
        $this->assertSame([null], array_column($board['recent'], 'amount'));
        $this->assertSame([], $this->getJson('/api/v1/reports', $pick)->json('data'));

        // Sales values alone open the sales reports, not the tax ones: those draw on purchases too.
        $pick = $this->member('Sales Analyst', ['dashboard.view', 'reports.view', 'sales.invoices.view', 'sales.values.view']);
        $this->assertSame('335.50', $this->getJson('/api/v1/dashboard', $pick)->json('data.sales.outstanding'));
        $keys = array_column($this->getJson('/api/v1/reports', $pick)->json('data'), 'key');
        $this->assertContains('sales-summary', $keys);
        $this->assertNotContains('tax-summary', $keys);

        $pick = $this->member('Tax Analyst', ['reports.view', 'sales.invoices.view', 'sales.values.view', 'purchases.purchases.view', 'purchases.values.view']);
        $this->assertContains('tax-summary', array_column($this->getJson('/api/v1/reports', $pick)->json('data'), 'key'));

        // A statement shows one side's values: a customer's, not a supplier's.
        $pick = $this->member('Statements', ['reports.view', 'contacts.view', 'sales.invoices.view', 'sales.values.view']);
        $this->getJson("/api/v1/reports/contact-statement?contact_id={$this->customer->id}&side=customer", $pick)->assertOk();
        $this->getJson("/api/v1/reports/contact-statement?contact_id={$this->customer->id}&side=supplier", $pick)->assertForbidden();
    }

    public function test_existing_roles_keep_the_values_they_saw(): void
    {
        $viewer = $this->business->roles()->create(['name' => 'Old Viewer', 'permissions' => ['dashboard.view', 'sales.invoices.view', 'purchases.payments.view']]);
        $other  = $this->business->roles()->create(['name' => 'Old Contacts', 'permissions' => ['contacts.view']]);
        $before = $this->business->fresh()->nav_version;

        (require database_path('migrations/2025_01_01_000106_add_value_permissions_to_roles.php'))->up();

        $this->assertSame(['dashboard.view', 'sales.invoices.view', 'purchases.payments.view', 'sales.values.view', 'purchases.values.view'], $viewer->fresh()->permissions);
        $this->assertSame(['contacts.view'], $other->fresh()->permissions);
        $this->assertGreaterThan($before, $this->business->fresh()->nav_version);
    }
}
