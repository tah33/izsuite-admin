<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase F4: the CRM part of a contact's profile - where they came
 * from, and their open, won and lost opportunities, counted in the base
 * currency at today's rate (EUR at 1.08 USD) - as the user may see them.
 * Monday 16 November 2026.
 */
class CrmContactSummaryTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $contact;

    private int $stage;

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
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->save();
        $this->business->exchangeRates()->create(['currency' => 'EUR', 'base_currency' => 'USD', 'rate' => '1.08', 'effective_date' => '2026-10-01']);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->contact = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $this->stage   = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
    }

    private function add(string $name, array $fields = []): int
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => $name, 'contact_id' => $this->contact->id, 'stage_id' => $this->stage, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20', ...$fields,
        ])->assertCreated()->json('data.id');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    private function summary(): array
    {
        return $this->getJson("/api/v1/crm/contacts/{$this->contact->id}/summary")->assertOk()->json('data');
    }

    public function test_a_contacts_crm_part_shows_where_they_came_from_and_counts_their_opportunities_in_the_base_currency(): void
    {
        // A lead converted into them, from the first lead source: its opportunity, 900 by the end of the year.
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0');
        $lead   = $this->postJson('/api/v1/crm/leads', ['name' => 'Karim Ahmed', 'phone' => '+8801711000000', 'source_id' => $source['id'], 'customer_need' => 'Shelving'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();
        $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => $this->contact->id, 'opportunity' => ['name' => 'Shelving', 'stage_id' => $this->stage, 'expected_value' => '900', 'currency' => 'USD', 'expected_close_date' => '2026-12-31']])->assertOk();

        $this->add('Racking');
        $this->add('Desks', ['currency' => 'EUR', 'expected_value' => '1000', 'expected_close_date' => '2026-12-10']);
        $won  = $this->add('Chairs');
        $lost = $this->add('Lockers');
        $gone = $this->add('Archived one');
        $this->postJson("/api/v1/crm/opportunities/{$won}/win", ['closed_on' => '2026-11-15', 'value' => '1500'])->assertOk();
        $this->postJson("/api/v1/crm/opportunities/{$lost}/lose", ['closed_on' => '2026-11-16', 'lost_reason_id' => $this->getJson('/api/v1/crm/opportunities/options')->json('data.lost_reasons.0.id')])->assertOk();
        $this->postJson("/api/v1/crm/opportunities/{$gone}/archive")->assertOk();

        $summary = $this->summary();
        $this->assertSame(['name' => $source['name'], 'lead' => ['id' => $lead, 'name' => 'Karim Ahmed']], $summary['source']);
        // Open: 900 + 2,000 + 1,000 EUR at 1.08 = 3,980; at 10% each, 398. Won: 1,500. The archived one is left out.
        $this->assertSame(['USD', ['count' => 3, 'value' => '3980.00', 'weighted' => '398.00', 'unrated' => 0], ['count' => 1, 'value' => '1500.00', 'unrated' => 0], ['count' => 1]], [$summary['currency'], $summary['open'], $summary['won'], $summary['lost']]);

        // The open ones by their closing day, then the ended, the latest first.
        $this->assertSame([
            ['Desks', 'open', 'New', 10, '1000.00', 'EUR', '2026-12-10'],
            ['Racking', 'open', 'New', 10, '2000.00', 'USD', '2026-12-20'],
            ['Shelving', 'open', 'New', 10, '900.00', 'USD', '2026-12-31'],
            ['Lockers', 'lost', 'Lost', 0, '2000.00', 'USD', '2026-11-16'],
            ['Chairs', 'won', 'Won', 100, '1500.00', 'USD', '2026-11-15'],
        ], array_map(fn (array $row) => [$row['name'], $row['status'], $row['stage'], $row['probability'], $row['value'], $row['currency'], $row['date']], $summary['opportunities']));
    }

    public function test_it_shows_only_what_the_user_sees(): void
    {
        $seller = $this->member(['contacts.view', 'crm.opportunities.view']);
        $this->add('Theirs', ['owner_id' => $seller->id]);
        $this->add('The owner\'s');

        // Their own opportunity only; no lead source without leads they see.
        Sanctum::actingAs($seller);
        $summary = $this->summary();
        $this->assertSame([null, 1, ['Theirs']], [$summary['source'], $summary['open']['count'], array_column($summary['opportunities'], 'name')]);

        // Contacts only: nothing of CRM's.
        Sanctum::actingAs($this->member(['contacts.view']));
        $this->assertSame([0, 0, 0, []], [$this->summary()['open']['count'], $this->summary()['won']['count'], $this->summary()['lost']['count'], $this->summary()['opportunities']]);

        Sanctum::actingAs($this->member(['crm.opportunities.view', 'crm.records.all']));
        $this->getJson("/api/v1/crm/contacts/{$this->contact->id}/summary")->assertForbidden();

        Sanctum::actingAs($this->user);
        $theirs = $this->businessOf(User::factory()->create())->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Theirs']);
        $this->getJson("/api/v1/crm/contacts/{$theirs->id}/summary")->assertNotFound();
    }
}
