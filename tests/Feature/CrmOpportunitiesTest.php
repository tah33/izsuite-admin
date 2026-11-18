<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\Shared\UserNotification;
use App\Models\User\Contact;
use App\Models\User\CrmOpportunity;
use App\Models\User\SalesTeam;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase D1: opportunities themselves (sections 17.12 to 17.14) -
 * added for an active contact at an open stage, owned, seen by whoever their
 * role lets see them, moved from stage to stage with the time at each kept,
 * their probability the stage's unless someone who may change it gives
 * another, and weighed in the base currency.
 */
class CrmOpportunitiesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $contact;

    /** @var array<string, int> the pipeline's stages by name */
    private array $stages;

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

        $this->contact = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Uddin Traders']);
        $this->stages  = collect($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages'))->pluck('id', 'name')->all();
    }

    private function add(array $fields = []): TestResponse
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - shelving', 'contact_id' => $this->contact->id, 'stage_id' => $this->stages['New'],
            'expected_value' => '2000', 'currency' => 'EUR', 'expected_close_date' => '2026-12-20', ...$fields,
        ]);
    }

    private function move(int $id, string $stage, array $fields = []): TestResponse
    {
        return $this->postJson("/api/v1/crm/opportunities/{$id}/stage", ['stage_id' => $this->stages[$stage], ...$fields]);
    }

    private function member(array $permissions, ?array $locations = null): User
    {
        $user   = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $member = $this->business->members()->create([
            'user_id'         => $user->id,
            'status'          => 'active',
            'role_id'         => $role->id,
            'location_access' => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
        ]);
        $member->locations()->sync($locations ?? []);

        return $user;
    }

    private function location(string $name): int
    {
        return $this->business->locations()->create(['user_id' => $this->user->id, 'name' => $name, 'code' => strtoupper(substr($name, 0, 3))])->id;
    }

    public function test_an_opportunity_is_added_at_an_open_stage_with_its_probability_and_weight(): void
    {
        $this->add([
            'stage_id' => $this->stages['Qualified'], 'contact_person' => 'Rahim Uddin', 'priority' => 'high', 'requirements' => 'Shelving for a new shop',
            'competitors' => ['Bose Ltd', ' bose ltd ', 'Karim & Co'], 'tags' => ['shop'], 'interested_items' => [['name' => 'Shelving', 'quantity' => '4']],
        ])->assertCreated()
            ->assertJsonPath('message', 'Opportunity added.')
            ->assertJsonPath('data.stage.name', 'Qualified')
            ->assertJsonPath('data.probability', 25)
            ->assertJsonPath('data.weighted_value', '500.00')
            ->assertJsonPath('data.base_weighted_value', '540.00')
            ->assertJsonPath('data.owner.id', $this->user->id)
            ->assertJsonPath('data.contact_person', 'Rahim Uddin')
            ->assertJsonPath('data.competitors', ['Bose Ltd', 'Karim & Co'])
            ->assertJsonPath('data.interested_items.0.name', 'Shelving')
            ->assertJsonPath('data.visits.0.stage', 'Qualified')
            ->assertJsonPath('data.actions', ['edit', 'stage', 'win', 'lose', 'archive']);

        // A possible sale: the supplier is a customer as well now.
        $this->assertSame('both', $this->contact->fresh()->role);
        $this->assertTrue(ActivityLog::where('description', 'Added opportunity "Uddin Traders - shelving" for contact "Uddin Traders"')->exists());

        $this->add(['name' => 'Late one', 'expected_close_date' => '2026-11-01', 'currency' => 'USD'])->assertCreated();

        $this->assertSame([
            ['Late one', 'Uddin Traders', 'New', 10, '200.00', '200.00', '2026-11-01', true],
            ['Uddin Traders - shelving', 'Uddin Traders', 'Qualified', 25, '500.00', '540.00', '2026-12-20', false],
        ], array_map(fn (array $row) => [$row['name'], $row['contact'], $row['stage'], $row['probability'], $row['weighted_value'], $row['base_weighted_value'], $row['expected_close_date'], $row['overdue']],
            $this->getJson('/api/v1/crm/opportunities')->assertOk()->json('data')));

        // Only at an open stage, and only for an active contact.
        $this->add(['stage_id' => $this->stages['Won']])->assertJsonValidationErrors(['stage_id' => "Choose an open stage of a pipeline switched on."]);
        $archived = $this->business->contacts()->create(['name' => 'Old Shop', 'is_active' => false]);
        $this->add(['contact_id' => $archived->id])->assertJsonValidationErrors(['contact_id' => 'Choose one of your active contacts.']);
    }

    public function test_moving_between_stages_keeps_each_stage_and_the_time_spent_there(): void
    {
        $id = $this->add()->assertCreated()->json('data.id');

        $this->travel(2)->days();
        $this->move($id, 'Proposal')->assertOk()
            ->assertJsonPath('message', 'Moved to Proposal.')
            ->assertJsonPath('data.probability', 50)
            ->assertJsonPath('data.weighted_value', '1000.00');

        $visits = $this->getJson("/api/v1/crm/opportunities/{$id}")->assertOk()->json('data.visits');
        $this->assertSame([['New', 172800, true], ['Proposal', 0, false]], array_map(fn (array $visit) => [$visit['stage'], $visit['seconds'], $visit['left_at'] !== null], $visits));
        $this->assertTrue(ActivityLog::where('description', 'Moved opportunity "Uddin Traders - shelving" from New to Proposal')->exists());

        $this->move($id, 'Proposal')->assertJsonValidationErrors(['stage_id' => 'It is at Proposal already.']);
        $this->move($id, 'Won')->assertJsonValidationErrors(['stage_id' => "Choose an open stage of a pipeline switched on."]);

        // Moving it is a permission of its own.
        Sanctum::actingAs($this->member(['crm.opportunities.view', 'crm.opportunities.manage', 'crm.records.all']));
        $this->move($id, 'Negotiation')->assertForbidden();

        // A stage it was at stays in the pipeline, switched off if need be.
        Sanctum::actingAs($this->user);
        $pipeline = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0');
        $without  = array_values(array_filter($pipeline['stages'], fn (array $stage) => $stage['name'] !== 'New'));
        $this->putJson("/api/v1/business-settings/crm/pipelines/{$pipeline['id']}", ['name' => $pipeline['name'], 'stages' => $without])
            ->assertJsonValidationErrors(['stages' => 'Opportunities are or were at "New", so it stays: switch it off instead.']);
    }

    public function test_a_probability_other_than_the_stages_needs_change_probability(): void
    {
        $seller = $this->member(['crm.opportunities.view', 'crm.opportunities.manage', 'crm.opportunities.stage']);
        Sanctum::actingAs($seller);

        $this->add(['probability' => 40])->assertJsonValidationErrors(['probability' => "The probability is the stage's: another needs Change Probability."]);
        $id = $this->add(['probability' => 10])->assertCreated()->json('data.id');
        $this->move($id, 'Qualified', ['probability' => 60])->assertJsonValidationErrors(['probability']);

        Sanctum::actingAs($this->user);
        $this->move($id, 'Qualified', ['probability' => 60])->assertOk()->assertJsonPath('data.probability', 60);

        // Editing keeps it - sent back as it is - but only someone who may change it changes it.
        Sanctum::actingAs($seller);
        $form = ['name' => 'Uddin Traders - shelving', 'contact_id' => $this->contact->id, 'expected_value' => '2500', 'currency' => 'EUR', 'expected_close_date' => '2026-12-20'];
        $this->putJson("/api/v1/crm/opportunities/{$id}", [...$form, 'probability' => 30])->assertJsonValidationErrors(['probability']);
        $this->putJson("/api/v1/crm/opportunities/{$id}", [...$form, 'probability' => 60])->assertOk()
            ->assertJsonPath('message', 'Opportunity saved.')
            ->assertJsonPath('data.probability', 60)
            ->assertJsonPath('data.weighted_value', '1500.00');
        $this->putJson("/api/v1/crm/opportunities/{$id}", [...$form, 'stage_id' => $this->stages['New']])->assertJsonValidationErrors(['stage_id' => 'An opportunity changes stage by moving it.']);
    }

    public function test_each_member_sees_the_opportunities_their_role_gives_them(): void
    {
        $dhaka = $this->location('Dhaka');
        $ctg   = $this->location('Chattogram');
        $ana   = $this->member(['crm.opportunities.view', 'crm.opportunities.manage']);
        $bob   = $this->member(['crm.opportunities.view', 'crm.records.team']);
        $cara  = $this->member(['crm.opportunities.view', 'crm.records.all'], [$dhaka]);
        $clerk = $this->member(['contacts.view']);
        SalesTeam::create(['workspace_id' => $this->business->id, 'name' => 'North', 'manager_id' => $bob->id])->members()->sync([$ana->id]);

        $anas = $this->add(['name' => 'Ana Deal', 'owner_id' => $ana->id, 'location_id' => $dhaka])->assertCreated()->json('data.id');
        $mine = $this->add(['name' => 'My Deal', 'location_id' => $ctg])->assertCreated()->json('data.id');
        $this->add(['owner_id' => $clerk->id])->assertJsonValidationErrors(['owner_id' => "Choose an active member who may view opportunities at this opportunity's location."]);

        $this->assertSame("{$this->user->name} gave you an opportunity: Ana Deal", UserNotification::query()->where('user_id', $ana->id)->sole()->title);

        $seen = function (User $user): array {
            Sanctum::actingAs($user);

            return collect($this->getJson('/api/v1/crm/opportunities')->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
        };

        $this->assertSame([$anas], $seen($ana));
        $this->assertSame([$anas], $seen($bob));
        $this->assertSame([$anas], $seen($cara));
        $this->assertSame([$anas, $mine], $seen($this->user));

        Sanctum::actingAs($ana);
        $this->getJson("/api/v1/crm/opportunities/{$mine}")->assertNotFound();

        // Given to Cara, who hears of it; the log says so.
        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/crm/opportunities/{$anas}", [
            'name' => 'Ana Deal', 'contact_id' => $this->contact->id, 'owner_id' => $cara->id, 'location_id' => $dhaka,
            'expected_value' => '2000', 'currency' => 'EUR', 'expected_close_date' => '2026-12-20',
        ])->assertOk()->assertJsonPath('data.owner.id', $cara->id);

        $this->assertSame(1, UserNotification::query()->where('user_id', $cara->id)->count());
        $this->assertTrue(ActivityLog::where('description', "Gave opportunity \"Ana Deal\" to {$cara->name}")->exists());
    }

    public function test_a_converted_leads_opportunity_has_its_items_and_its_first_stage(): void
    {
        $lead = $this->postJson('/api/v1/crm/leads', [
            'name' => 'Rahim Uddin', 'email' => 'rahim@uddin.example', 'customer_need' => 'Shelving',
            'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id'),
            'interested_items' => [['name' => 'Shelving', 'quantity' => '4']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();
        $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => $this->contact->id, 'opportunity' => [
            'name' => 'Rahim - shelving', 'stage_id' => $this->stages['Qualified'], 'expected_value' => '1500', 'currency' => 'USD', 'expected_close_date' => '2026-12-20',
        ]])->assertOk();

        $this->getJson('/api/v1/crm/opportunities/'.CrmOpportunity::sole()->id)->assertOk()
            ->assertJsonPath('data.lead.id', $lead)
            ->assertJsonPath('data.interested_items.0.name', 'Shelving')
            ->assertJsonPath('data.interested_items.0.quantity', '4.000')
            ->assertJsonPath('data.visits.0.stage', 'Qualified')
            ->assertJsonPath('data.base_weighted_value', '375.00');
    }
}
