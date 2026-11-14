<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Frontend\MenuItem;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Documents\DocumentRenderer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase A: the full Sales app taking over from Sales Lite - its menu
 * in Doc 16's order, the Sales Overview, sales teams, and on each document
 * its salesperson, team, own addresses and delivery details - while Sales
 * Lite leaves those as they are. Wednesday 28 October 2026.
 */
class SalesFullEditionTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        foreach (['Invoices' => '/sales/invoices', 'Quotations' => '/sales/quotations', 'Proforma Invoices' => '/sales/proformas', 'Credit Notes' => '/sales/credit-notes', 'Payments Received' => '/sales/payments'] as $label => $route) {
            MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'lite', 'label' => $label, 'route' => $route, 'sort_order' => 10 + MenuItem::count()]);
        }
        MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'full', 'label' => 'Sales Orders', 'route' => '/sales/orders', 'sort_order' => 90]);
        MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'full', 'label' => 'Overview', 'route' => '/sales/overview', 'sort_order' => 91, 'permission_key' => 'sales.values.view']);
        MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'full', 'label' => 'Customers', 'route' => '/contacts?role=customer', 'sort_order' => 92, 'permission_key' => 'contacts.view']);

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** @return list<string> the Sales entry's pages, as the sidebar lists them */
    private function salesMenu(): array
    {
        $sales = collect($this->getJson('/api/v1/navigation')->assertOk()->json('data.menu.apps'))->firstWhere('family', 'sales');

        return array_column($sales['children'] ?? [], 'label');
    }

    private function team(string $name, array $members = []): int
    {
        return $this->postJson('/api/v1/business-settings/sales-teams', ['name' => $name, 'member_ids' => $members])->assertCreated()->json('data.id');
    }

    public function test_full_sales_lists_its_pages_as_doc_16_does_and_lite_keeps_doc_08s_order(): void
    {
        $this->assertSame(['Invoices', 'Quotations', 'Proforma Invoices', 'Credit Notes', 'Payments Received'], $this->salesMenu());

        $this->full();

        $this->assertSame(['Overview', 'Quotations', 'Proforma Invoices', 'Sales Orders', 'Invoices', 'Credit Notes', 'Payments Received', 'Customers'], $this->salesMenu());
        $this->assertSame('/contacts?role=customer', collect($this->getJson('/api/v1/navigation')->json('data.menu.apps'))->firstWhere('family', 'sales')['children'][7]['route']);
    }

    public function test_a_document_keeps_its_salesperson_team_addresses_and_delivery_details(): void
    {
        $this->full();
        $team = $this->team('Dhaka North', [$this->user->id]);

        $this->getJson('/api/v1/sales/salespeople')->assertOk()
            ->assertJsonPath('data.people.0.id', $this->user->id)
            ->assertJsonPath('data.teams.0.name', 'Dhaka North')
            ->assertJsonPath('data.teams.0.member_ids', [$this->user->id]);

        $shipTo = ['line1' => 'Warehouse 4, Tejgaon', 'line2' => '', 'city' => 'Dhaka', 'state' => '', 'postal_code' => '1208', 'country' => 'BD'];
        $id     = $this->draft('quotations', [
            'salesperson_id' => $this->user->id, 'sales_team_id' => $team, 'shipping_address' => $shipTo, 'billing_address' => ['line1' => '', 'city' => ''],
            'expected_delivery_date' => '2026-11-10', 'shipping_method' => ' Courier ', 'shipping_terms' => 'DAP',
        ]);

        $this->getJson("/api/v1/sales/quotations/{$id}")->assertOk()
            ->assertJsonPath('data.salesperson.id', $this->user->id)
            ->assertJsonPath('data.sales_team.name', 'Dhaka North')
            ->assertJsonPath('data.shipping_address.line1', 'Warehouse 4, Tejgaon')
            ->assertJsonPath('data.shipping_address.line2', null)
            ->assertJsonPath('data.billing_address', null)
            ->assertJsonPath('data.expected_delivery_date', '2026-11-10')
            ->assertJsonPath('data.shipping_method', 'Courier');

        // The PDF says who sold it, when and how it goes, and where.
        $html = app(DocumentRenderer::class)->sales($this->business, SalesDocument::find($id))->html;
        foreach (['Salesperson', 'Expected Delivery', 'Courier', 'DAP', 'Ship To', 'Warehouse 4, Tejgaon', '1208'] as $printed) {
            $this->assertStringContainsString($printed, $html);
        }

        // Converted, the invoice keeps them; credited, so does its credit note.
        $invoice = $this->postJson("/api/v1/sales/quotations/{$id}/convert", ['to' => 'invoice'])->assertSuccessful()->json('data.id');
        $this->assertSame([$this->user->id, $team, '2026-11-10'], [SalesDocument::find($invoice)->salesperson_id, SalesDocument::find($invoice)->sales_team_id, SalesDocument::find($invoice)->expected_delivery_date]);

        // Not the business's own: refused.
        $this->postJson('/api/v1/sales/quotations', $this->quotation(['salesperson_id' => User::factory()->create()->id, 'sales_team_id' => 9999]))
            ->assertJsonValidationErrors(['salesperson_id', 'sales_team_id']);
    }

    public function test_sales_lite_leaves_the_full_apps_fields_as_they_are(): void
    {
        $this->full();
        $id = $this->draft('invoices', ['salesperson_id' => $this->user->id, 'shipping_method' => 'Courier']);

        // Turned off, Sales Lite is back.
        $this->postJson('/api/v1/apps/sales/deactivate')->assertOk()->assertJsonPath('message', 'Sales is off. Sales Lite is back.');

        // Sales Lite's form sends none of them; anything sent is left out.
        $this->putJson("/api/v1/sales/invoices/{$id}", $this->invoice(['salesperson_id' => null, 'shipping_method' => 'Post']))->assertOk();
        $this->assertSame([$this->user->id, 'Courier'], [SalesDocument::find($id)->salesperson_id, SalesDocument::find($id)->shipping_method]);

        // The full app's pages are not there.
        $this->getJson('/api/v1/sales/overview')->assertForbidden();
        $this->getJson('/api/v1/business-settings/sales-teams')->assertForbidden();
        $this->assertSame(['Invoices', 'Quotations', 'Proforma Invoices', 'Credit Notes', 'Payments Received'], $this->salesMenu());
    }

    public function test_sales_teams_have_their_own_names_active_members_and_stay_once_used(): void
    {
        $this->full();
        $id = $this->team('Dhaka North', [$this->user->id]);

        $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'Dhaka North', 'member_ids' => []])->assertJsonValidationErrors(['name' => 'Another sales team has this name.']);
        $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'Chattogram', 'member_ids' => [User::factory()->create()->id]])->assertJsonValidationErrors(['member_ids']);

        // Named on a document, it is switched off rather than deleted.
        $this->draft('quotations', ['sales_team_id' => $id]);
        $this->deleteJson("/api/v1/business-settings/sales-teams/{$id}")->assertJsonValidationErrors(['team' => 'switch it off instead']);
        $this->putJson("/api/v1/business-settings/sales-teams/{$id}", ['name' => 'Dhaka North', 'is_active' => false, 'member_ids' => [$this->user->id]])->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/business-settings/sales-teams')->assertOk()->assertJsonPath('data.0.documents_count', 1);
        $this->getJson('/api/v1/sales/salespeople')->assertJsonPath('data.teams', []);

        $unused = $this->team('Sylhet');
        $this->deleteJson("/api/v1/business-settings/sales-teams/{$unused}")->assertOk();
    }

    public function test_the_overview_counts_quotations_apart_from_sales_and_follows_its_filters(): void
    {
        $this->full();
        $other = User::factory()->create();
        $staff = $this->business->roles()->create(['name' => 'Seller', 'permissions' => ['sales.invoices.view']]);
        $this->business->members()->create(['user_id' => $other->id, 'status' => 'active', 'role_id' => $staff->id]);

        // A quotation sent, one sent and accepted, one left a draft; two invoices issued, one by each salesperson, the second part paid.
        $quote = $this->draft('quotations', ['salesperson_id' => $this->user->id]);
        $this->postJson("/api/v1/sales/quotations/{$quote}/send")->assertSuccessful();
        $won = $this->draft('quotations', ['salesperson_id' => $this->user->id]);
        $this->postJson("/api/v1/sales/quotations/{$won}/send")->assertSuccessful();
        $this->postJson("/api/v1/sales/quotations/{$won}/accept")->assertSuccessful();
        $this->draft('quotations');

        foreach ([$this->user->id, $other->id] as $seller) {
            $id = $this->draft('invoices', ['salesperson_id' => $seller]);
            $this->postJson("/api/v1/sales/invoices/{$id}/issue")->assertSuccessful();
        }
        $this->pay($id, $this->account(), '100.00')->assertCreated();

        $this->getJson('/api/v1/sales/overview?date_from=2026-10-01&date_to=2026-10-31')->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.quotations', ['count' => 2, 'value' => '671.00'])
            ->assertJsonPath('data.accepted_quotations', ['count' => 1, 'value' => '335.50'])
            ->assertJsonPath('data.sales', ['count' => 2, 'value' => '590.00'])
            ->assertJsonPath('data.received', '100.00')
            ->assertJsonPath('data.outstanding.count', 2)
            ->assertJsonPath('data.credit_notes.count', 0);

        $this->getJson("/api/v1/sales/overview?date_from=2026-10-01&date_to=2026-10-31&salesperson_id={$other->id}")->assertOk()
            ->assertJsonPath('data.quotations.count', 0)
            ->assertJsonPath('data.sales', ['count' => 1, 'value' => '295.00'])
            ->assertJsonPath('data.received', '100.00');

        // The payment was for the other salesperson's invoice.
        $this->getJson("/api/v1/sales/overview?date_from=2026-10-01&date_to=2026-10-31&salesperson_id={$this->user->id}")->assertOk()
            ->assertJsonPath('data.accepted_quotations.count', 1)
            ->assertJsonPath('data.received', '0.00');

        // Seeing it needs the sales values; with them, its salesperson and team filters load too.
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/sales/overview')->assertForbidden();
        $this->getJson('/api/v1/sales/salespeople')->assertForbidden();

        $staff->update(['permissions' => ['sales.invoices.view', 'sales.values.view']]);
        $this->getJson('/api/v1/sales/overview')->assertOk();
        $this->getJson('/api/v1/sales/salespeople')->assertOk()->assertJsonCount(2, 'data.people');
    }
}
