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
 * What a member sees (Doc 12 sections 13 and 21) - Doc 12 Phase C1: a
 * record offers only the actions the role allows, and the Dashboard and
 * Reports show only what the role may see. The owner sees everything.
 */
class RoleScreensTest extends TestCase
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

    /** Signs a new member in under $role - a template's name, or a role made with $permissions. */
    private function member(string $role, array $permissions = []): array
    {
        $held = $this->business->roles()->where('name', $role)->first()
            ?? $this->business->roles()->create(['name' => $role, 'permissions' => Permissions::expand($permissions)]);

        $member = User::factory()->create();
        $this->business->members()->create(['user_id' => $member->id, 'role_id' => $held->id, 'name' => $member->name, 'email' => $member->email]);
        Sanctum::actingAs($member);

        return $this->pick($this->business);
    }

    public function test_a_record_offers_only_the_actions_the_role_allows(): void
    {
        $id = $this->issuedInvoice();

        $owner = $this->getJson("/api/v1/sales/invoices/{$id}")->assertOk()->json('data');
        $this->assertSame(['record_payment', 'credit_note', 'void'], $owner['actions']);
        $this->assertContains('email', $owner['delivery']);

        // A Sales User records payments and sends invoices - no credit notes, no voiding.
        $pick  = $this->member('Sales User');
        $sales = $this->getJson("/api/v1/sales/invoices/{$id}", $pick)->assertOk()->json('data');
        $this->assertSame(['record_payment'], $sales['actions']);
        $this->assertSame($owner['delivery'], $sales['delivery']);

        // Seeing it is all a viewer does - in the list and the Documents page too.
        $pick = $this->member('Invoice Viewer', ['sales.invoices.view', 'documents.view']);
        $this->getJson("/api/v1/sales/invoices/{$id}", $pick)->assertJsonPath('data.actions', [])->assertJsonPath('data.delivery', []);
        $this->getJson('/api/v1/sales/invoices', $pick)->assertJsonPath('data.0.actions', [])->assertJsonPath('data.0.delivery', []);
        $this->getJson('/api/v1/documents', $pick)->assertJsonPath('data.0.actions', []);
    }

    public function test_the_dashboard_shows_what_the_role_may_see(): void
    {
        $this->issuedInvoice();
        $this->draft('quotations');

        $owner = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
        $this->assertNotNull($owner['sales']);
        $this->assertNotNull($owner['purchases']);
        $this->assertNotNull($owner['accounts']);
        $this->assertSame(['Invoice', 'Quotation'], $this->kindsIn($owner));

        $pick  = $this->member('Sales User');
        $sales = $this->getJson('/api/v1/dashboard', $pick)->assertOk()->json('data');
        $this->assertSame('335.50', $sales['sales']['outstanding']);
        $this->assertNull($sales['purchases']);
        $this->assertNotNull($sales['accounts']);
        $this->assertSame(['Invoice', 'Quotation'], $this->kindsIn($sales));

        // The newest transactions are only of the kinds the role may open -
        // quotations without the invoices' figures, too.
        $pick = $this->member('Invoices Only', ['dashboard.view', 'sales.invoices.view']);
        $this->assertSame(['Invoice'], $this->kindsIn($this->getJson('/api/v1/dashboard', $pick)->assertOk()->json('data')));

        $pick   = $this->member('Quotations Only', ['dashboard.view', 'sales.quotations.view']);
        $quotes = $this->getJson('/api/v1/dashboard', $pick)->assertOk()->json('data');
        $this->assertNull($quotes['sales']);
        $this->assertSame(['Quotation'], $this->kindsIn($quotes));

        $pick = $this->member('Glance', ['dashboard.view']);
        $none = $this->getJson('/api/v1/dashboard', $pick)->assertOk()->json('data');
        $this->assertSame([null, null, null, null, [], null], [$none['sales'], $none['purchases'], $none['expenses'], $none['chart'], $none['recent'], $none['accounts']]);
    }

    /** The kinds of record among the Dashboard's newest transactions, A to Z. */
    private function kindsIn(array $dashboard): array
    {
        $kinds = array_values(array_unique(array_column($dashboard['recent'], 'type')));
        sort($kinds);

        return $kinds;
    }

    public function test_lists_across_apps_show_only_the_kinds_the_role_may_view(): void
    {
        $this->issuedInvoice();
        $supplier = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Karim Supplies', 'billing_country' => 'BD']);
        $this->postJson('/api/v1/purchases', [
            'contact_id'    => $supplier->id,
            'document_date' => '2026-10-28',
            'currency'      => 'USD',
            'lines'         => [['item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => '1', 'unit' => 'Piece', 'unit_price' => '90.00']],
        ])->assertCreated();

        $kinds = fn (string $url, array $pick = []) => collect($this->getJson($url, $pick)->assertOk()->json('data'))->pluck('kind')->unique()->sort()->values()->all();

        $this->assertSame(['invoices', 'purchases'], $kinds('/api/v1/documents'));
        $this->assertSame(['invoices', 'purchases'], $kinds("/api/v1/items/{$this->chair->id}/transactions"));

        // A Sales User sees the sales side alone: the purchase is not theirs to open.
        $pick = $this->member('Sales User');
        $this->assertSame(['invoices'], $kinds('/api/v1/documents', $pick));
        $this->assertSame(['invoices'], $kinds("/api/v1/items/{$this->chair->id}/transactions", $pick));
    }

    public function test_reports_list_only_what_the_role_may_see(): void
    {
        $owner = array_column($this->getJson('/api/v1/reports')->assertOk()->json('data'), 'key');
        $this->assertContains('account-transactions', $owner);

        $pick = $this->member('Sales Analyst', ['reports.view', 'sales.invoices.view', 'sales.values.view']);
        $keys = array_column($this->getJson('/api/v1/reports', $pick)->assertOk()->json('data'), 'key');

        // No Contact Statement without Contacts' View, no accounts or payables,
        // and no tax reports: they draw on purchases too, unseen here.
        $this->assertSame(
            ['sales-summary', 'sales-trend', 'top-items', 'top-customers', 'outstanding-invoices', 'payments-received', 'credit-notes'],
            $keys,
        );
        $this->getJson('/api/v1/reports/account-transactions', $pick)->assertNotFound();
        $this->getJson('/api/v1/reports/sales-summary?range=this_month', $pick)->assertOk();
    }
}
