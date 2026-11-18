<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase F1: sales documents made from an opportunity - starting with
 * its contact, currency and interested items, linked to it and to what is
 * converted from them, listed on its page and its timeline as the user may
 * see them, and made only from an open or won opportunity the user sees,
 * for its contact. Monday 16 November 2026, Sales Lite; the customer is
 * MakesSalesDocuments' Uddin Traders.
 */
class CrmOpportunityDocumentsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->setUpSales();
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $stage             = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $this->opportunity = $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - chairs', 'contact_id' => $this->customer->id, 'stage_id' => $stage, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20',
            'interested_items' => [['item_id' => $this->chair->id, 'quantity' => '2'], ['name' => 'Installation', 'quantity' => '1']],
        ])->assertCreated()->json('data.id');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    private function documents(): array
    {
        return $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/documents")->assertOk()->json('data');
    }

    public function test_a_quotation_made_from_an_opportunity_starts_with_it_and_its_link_goes_with_what_it_becomes(): void
    {
        // What the form starts with: its customer, currency and interested items - the chair at its price and tax, the installation unpriced.
        $defaults = $this->getJson("/api/v1/sales/quotations/defaults?opportunity_id={$this->opportunity}")->assertOk()->json('data');
        $this->assertSame([['id' => $this->customer->id, 'name' => 'Uddin Traders'], 'USD'], [$defaults['customer'], $defaults['currency']]);
        $this->assertSame([$this->opportunity, 'Uddin Traders - chairs', null, null], [$defaults['opportunity']['id'], $defaults['opportunity']['name'], $defaults['opportunity']['salesperson_id'], $defaults['opportunity']['sales_team_id']]);
        $this->assertSame([
            ['item_id' => $this->chair->id, 'name' => 'Office Chair', 'description' => null, 'unit' => 'Piece', 'unit_price' => '150.00', 'tax_rate_id' => $this->vat->id, 'quantity' => '2.000'],
            ['item_id' => null, 'name' => 'Installation', 'description' => null, 'unit' => null, 'unit_price' => null, 'tax_rate_id' => null, 'quantity' => '1.000'],
        ], $defaults['opportunity']['lines']);

        $quotation = $this->postJson('/api/v1/sales/quotations', $this->quotation(['opportunity_id' => $this->opportunity]))->assertCreated()
            ->assertJsonPath('data.opportunity', ['id' => $this->opportunity, 'name' => 'Uddin Traders - chairs'])
            ->json('data.id');
        $this->assertSame([['quotations', 'draft', '335.50', 'USD']], array_map(fn (array $row) => [$row['kind'], $row['status'], $row['total'], $row['currency']], $this->documents()));

        // Sent, then converted: the invoice is linked too, and the quotation stays.
        $this->travel(5)->minutes();
        $this->postJson("/api/v1/sales/quotations/{$quotation}/send")->assertOk();
        $this->travel(5)->minutes();
        $invoice = $this->postJson("/api/v1/sales/quotations/{$quotation}/convert", ['to' => 'invoice'])->assertCreated()
            ->assertJsonPath('data.opportunity.id', $this->opportunity)
            ->json('data.id');
        $this->assertSame([[$invoice, 'invoices', 'draft'], [$quotation, 'quotations', 'converted']], array_map(fn (array $row) => [$row['id'], $row['kind'], $row['status']], $this->documents()));

        // Its timeline: the invoice made, the quotation sent and made, newest first.
        $timeline = $this->getJson("/api/v1/crm/timeline/opportunity/{$this->opportunity}?show=documents")->assertOk()->json('data');
        $this->assertSame([['invoices', 'made'], ['quotations', 'sent'], ['quotations', 'made']], array_map(fn (array $entry) => [$entry['document']['kind'], $entry['step']], $timeline));
        $this->assertSame(['2026-11-16T10:10:00+00:00', '2026-11-16T10:05:00+00:00', null], [$timeline[0]['at'], $timeline[1]['at'], $timeline[1]['by']]);

        // Its value is its own: the documents add nothing to it.
        $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}")->assertJsonPath('data.expected_value', '2000.00');
    }

    public function test_one_is_made_only_from_an_open_or_won_opportunity_the_user_sees_and_for_its_contact(): void
    {
        $other = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Karim Stores']);
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['contact_id' => $other->id, 'opportunity_id' => $this->opportunity]))->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id' => 'A document made from an opportunity is for its contact.']);

        $reason = $this->getJson('/api/v1/crm/opportunities/options')->json('data.lost_reasons.0.id');
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/lose", ['closed_on' => '2026-11-16', 'lost_reason_id' => $reason])->assertOk();
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['opportunity_id' => $this->opportunity]))->assertUnprocessable()
            ->assertJsonValidationErrors(['opportunity_id' => 'This opportunity was lost. Reopen it before making documents from it.']);
        $this->assertNull($this->getJson("/api/v1/sales/invoices/defaults?opportunity_id={$this->opportunity}")->json('data.opportunity'));

        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/reopen")->assertOk();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/win", ['closed_on' => '2026-11-16'])->assertOk();
        $invoice = $this->postJson('/api/v1/sales/invoices', $this->invoice(['opportunity_id' => $this->opportunity]))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/archive")->assertOk();
        $this->postJson('/api/v1/sales/quotations', $this->quotation(['opportunity_id' => $this->opportunity]))->assertUnprocessable()
            ->assertJsonValidationErrors(['opportunity_id' => 'This opportunity is archived. Restore it before making documents from it.']);

        // A saved one keeps its own link.
        $this->putJson("/api/v1/sales/invoices/{$invoice}", $this->invoice(['opportunity_id' => null]))->assertOk()->assertJsonPath('data.opportunity.id', $this->opportunity);

        // Not from one the user does not see - another business's, or one they may not view - nor while CRM is off.
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/restore")->assertOk();
        Sanctum::actingAs($this->member(['sales.quotations.view', 'sales.quotations.create', 'crm.opportunities.view']));
        $this->postJson('/api/v1/sales/quotations', $this->quotation(['opportunity_id' => $this->opportunity]))->assertUnprocessable()
            ->assertJsonValidationErrors(['opportunity_id' => 'Choose an opportunity you can see, while CRM is on.']);
        $this->postJson('/api/v1/sales/quotations', $this->quotation())->assertCreated()->assertJsonPath('data.opportunity', null);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/apps/crm/deactivate')->assertOk();
        $this->postJson('/api/v1/sales/quotations', $this->quotation(['opportunity_id' => $this->opportunity]))->assertUnprocessable()->assertJsonValidationErrors('opportunity_id');
    }

    public function test_its_documents_show_as_the_user_may_see_them(): void
    {
        $quotation = $this->postJson('/api/v1/sales/quotations', $this->quotation(['opportunity_id' => $this->opportunity]))->assertCreated()->json('data.id');
        $invoice   = $this->postJson('/api/v1/sales/invoices', $this->invoice(['opportunity_id' => $this->opportunity]))->assertCreated()->json('data.id');

        // Only the kinds the member views, and no totals without Sales' values.
        Sanctum::actingAs($this->member(['crm.opportunities.view', 'crm.records.all', 'sales.invoices.view']));
        $this->assertSame([[$invoice, null]], array_map(fn (array $row) => [$row['id'], $row['total']], $this->documents()));
        $this->getJson("/api/v1/sales/invoices/{$invoice}")->assertOk()->assertJsonPath('data.opportunity.name', 'Uddin Traders - chairs');

        // Someone who does not see the opportunity: no list, and no link on the document's page.
        Sanctum::actingAs($this->member(['crm.opportunities.view', 'sales.quotations.view']));
        $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/documents")->assertNotFound();
        $this->getJson("/api/v1/sales/quotations/{$quotation}")->assertOk()->assertJsonPath('data.opportunity', null);
        Sanctum::actingAs($this->member(['sales.quotations.view']));
        $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/documents")->assertForbidden();
    }
}
