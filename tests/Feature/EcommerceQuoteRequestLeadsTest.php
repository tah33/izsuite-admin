<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\UserNotification;
use App\Models\User\CrmLead;
use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A request for a quotation becomes a CRM lead (Doc 19 section 19.30 - Phase
 * H4b), while Settings → eCommerce says so and CRM is on: the customer's
 * details, from Website, at the storefront's location, its products the
 * lead's interested items - in the queue, or given an owner by the
 * assignment rules, who is told. The business's required custom fields wait
 * for a member. The request keeps its lead, and names it to whoever may
 * open it.
 */
class EcommerceQuoteRequestLeadsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    private const SETTINGS = '/api/v1/business-settings/ecommerce';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-28 10:00:00');
        $this->openStorefront(['Custom Shelf' => ['sale_price' => '90.00']]);
        $this->listed('Custom Shelf', ['sales_method' => 'quotation']);
    }

    private function crmOn(): void
    {
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::first()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    /** Karim's request for 4 Custom Shelves, from the store. */
    private function ask()
    {
        return $this->asCustomer(null)->postJson(self::STORE.'/quote-requests', [
            'name' => 'Karim Rahman', 'email' => 'karim@example.test', 'phone' => '+8801700000001', 'company' => 'Rahman Traders',
            'delivery' => ['country' => 'BD', 'city' => 'Dhaka', 'address' => 'House 12, Road 5'],
            'required_on' => '2026-12-10', 'message' => 'Oak, if you have it.',
            'items' => [['product' => 'custom-shelf', 'quantity' => 4]],
        ]);
    }

    public function test_while_crm_is_on_a_request_becomes_a_lead_in_the_queue(): void
    {
        // Without CRM, the request alone.
        $this->ask()->assertCreated();
        $this->assertDatabaseCount('crm_leads', 0);

        $this->crmOn();
        $this->ask()->assertCreated();

        $request = EcommerceQuoteRequest::where('number', 'QR-00002')->sole();
        $lead    = CrmLead::with(['interestedItems', 'source'])->sole();
        $this->assertSame($lead->id, $request->crm_lead_id);
        $this->assertSame(
            ['Karim Rahman', 'Rahman Traders', 'karim@example.test', '+8801700000001', 'Website', 'Rahman Shop: QR-00002', $this->store->location_id, CrmLead::NEW, null, '2026-12-10', 'House 12, Road 5', 'Dhaka', 'BD', 'Oak, if you have it.'],
            [$lead->name, $lead->business_name, $lead->email, $lead->phone, $lead->source?->name, $lead->source_details, $lead->location_id, $lead->status, $lead->owner_id, $lead->expected_purchase_date?->toDateString(), $lead->address_line1, $lead->address_city, $lead->address_country, $lead->notes],
        );
        $this->assertSame([[$this->items['Custom Shelf']->id, 'Custom Shelf', '4.000']], $lead->interestedItems->map(fn ($item) => [$item->item_id, $item->name, $item->quantity])->all());
        $this->assertDatabaseHas('activity_logs', ['description' => 'Added the lead Karim Rahman from the request for a quotation QR-00002 on Rahman Shop']);

        // The Quote Requests page names it; CRM shows it as any lead.
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/ecommerce/quote-requests')->assertJsonPath('data.0.lead_id', $lead->id)->assertJsonPath('data.1.lead_id', null);
        $this->getJson("/api/v1/ecommerce/quote-requests/{$request->id}")->assertJsonPath('data.lead_id', $lead->id);
        $this->getJson("/api/v1/crm/leads/{$lead->id}")->assertOk()->assertJsonPath('data.name', 'Karim Rahman');

        // Settings → eCommerce switches it off.
        $this->getJson(self::SETTINGS)->assertJsonPath('data.quote_requests_to_leads', true);
        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'quote_requests_to_leads' => false])
            ->assertOk()->assertJsonPath('data.quote_requests_to_leads', false);
        $this->ask()->assertCreated();
        $this->assertDatabaseCount('crm_leads', 1);
    }

    public function test_an_assignment_rule_gives_it_its_owner_who_is_told(): void
    {
        $this->crmOn();
        $this->postJson('/api/v1/business-settings/crm/assignment-rules', ['name' => 'Shelves', 'item_id' => $this->items['Custom Shelf']->id, 'user_id' => $this->user->id])->assertCreated();

        $this->ask()->assertCreated();

        $lead = CrmLead::sole();
        $this->assertSame([CrmLead::ASSIGNED, $this->user->id], [$lead->status, $lead->owner_id]);
        $this->assertNotNull($lead->first_assigned_at);

        $notice = UserNotification::query()->where('user_id', $this->user->id)->where('trigger', 'leads.assigned')->sole();
        $this->assertSame(['A lead from Rahman Shop: Karim Rahman', "/crm/leads/{$lead->id}"], [$notice->title, $notice->link]);
    }

    public function test_the_business_required_custom_fields_wait_for_a_member(): void
    {
        $this->crmOn();
        $this->postJson('/api/v1/business-settings/crm/custom-fields', ['record' => 'lead', 'name' => 'Industry', 'type' => 'text', 'is_required' => true])->assertCreated();

        $this->ask()->assertCreated();

        $this->assertSame([], CrmLead::sole()->custom_fields);
    }

    public function test_a_request_names_its_lead_only_to_whoever_may_open_it(): void
    {
        $this->crmOn();
        $this->ask()->assertCreated();
        $lead = CrmLead::sole();
        $id   = EcommerceQuoteRequest::sole()->id;

        // Orders but not leads; leads but not the queue it is in: no lead named, as CRM would not open it.
        foreach ([[['ecommerce.orders.view'], 403], [['ecommerce.orders.view', 'crm.leads.view'], 404]] as [$permissions, $crm]) {
            Sanctum::actingAs($viewer = $this->member($permissions));
            $this->getJson('/api/v1/ecommerce/quote-requests')->assertOk()->assertJsonPath('data.0.lead_id', null);
            $this->getJson("/api/v1/ecommerce/quote-requests/{$id}")->assertOk()->assertJsonPath('data.lead_id', null);
            $this->getJson("/api/v1/crm/leads/{$lead->id}")->assertStatus($crm);
        }

        // Once it is theirs, the last of them sees it; so does whoever may assign leads, who sees the queue.
        $lead->forceFill(['owner_id' => $viewer->id, 'status' => CrmLead::ASSIGNED])->save();
        $this->getJson("/api/v1/ecommerce/quote-requests/{$id}")->assertJsonPath('data.lead_id', $lead->id);
        $lead->forceFill(['owner_id' => null, 'status' => CrmLead::NEW])->save();
        Sanctum::actingAs($this->member(['ecommerce.orders.view', 'crm.leads.view', 'crm.leads.assign']));
        $this->getJson('/api/v1/ecommerce/quote-requests')->assertJsonPath('data.0.lead_id', $lead->id);
        $this->getJson("/api/v1/crm/leads/{$lead->id}")->assertOk();
    }
}
