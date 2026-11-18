<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\Shared\UserNotification;
use App\Models\User\CrmLead;
use App\Models\User\SalesTeam;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase B1: leads - added, edited, assigned by hand with the new
 * owner told, taken through their statuses, and seen by their owner, their
 * team or everyone as the role says, only at the locations a member may use.
 */
class CrmLeadsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
    }

    /** The id of the lead source called $name. */
    private function source(string $name = 'Website'): int
    {
        return collect($this->getJson('/api/v1/crm/leads/options')->assertOk()->json('data.sources'))->firstWhere('name', $name)['id'];
    }

    private function add(array $fields = []): TestResponse
    {
        return $this->postJson('/api/v1/crm/leads', ['name' => 'Rahim Uddin', 'email' => 'rahim@example.com', 'source_id' => $this->source(), ...$fields]);
    }

    /** A member of the business whose role holds $permissions - at $locations only, when given. */
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

    public function test_a_lead_is_added_owned_by_whoever_adds_it(): void
    {
        $options = $this->getJson('/api/v1/crm/leads/options')->assertOk()->json('data');
        $this->assertCount(12, $options['sources']);
        $this->assertSame([['id' => $this->user->id, 'name' => $this->user->name, 'locations' => null]], $options['owners']);
        $this->assertSame($this->user->id, $options['owner_id']);

        $lead = $this->add(['business_name' => 'Uddin Traders', 'estimated_value' => '1500.50', 'tags' => [' Wholesale ', 'wholesale', ''], 'priority' => 'high'])
            ->assertCreated()
            ->assertJsonPath('message', 'Lead added.')
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.owner', ['id' => $this->user->id, 'name' => $this->user->name])
            ->assertJsonPath('data.source.name', 'Website')
            ->assertJsonPath('data.currency', $this->business->base_currency)
            ->assertJsonPath('data.tags', ['Wholesale'])
            ->json('data');

        $this->assertSame(['edit', 'assign', 'contact', 'qualify', 'unqualify', 'archive'], $lead['actions']);
        $this->assertTrue(ActivityLog::query()->where('description', 'Added lead "Rahim Uddin"')->exists());

        // Open leads, newest first; searched by business.
        $this->getJson('/api/v1/crm/leads?search=Uddin Trad')->assertOk()
            ->assertJsonPath('data.0.id', $lead['id'])
            ->assertJsonPath('data.0.owner', $this->user->name)
            ->assertJsonPath('data.0.source', 'Website');

        // Changed: its fields, never its owner.
        $this->putJson("/api/v1/crm/leads/{$lead['id']}", ['name' => 'Rahim Uddin', 'phone' => '+880 1711-000000', 'source_id' => $this->source('Referral'), 'source_details' => 'Karim Ali'])
            ->assertOk()->assertJsonPath('data.source.name', 'Referral')->assertJsonPath('data.email', 'rahim@example.com');
        $this->putJson("/api/v1/crm/leads/{$lead['id']}", ['name' => 'Rahim', 'phone' => '01711', 'source_id' => $this->source(), 'owner_id' => $this->user->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['owner_id' => 'A lead changes hands by assigning it.']);
    }

    public function test_a_lead_needs_a_name_a_way_to_reach_it_and_a_source(): void
    {
        $this->postJson('/api/v1/crm/leads', ['name' => '', 'source_id' => null])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email' => 'Give a phone number or an e-mail address.', 'source_id' => 'Choose where the lead came from.']);

        // A lost reason is no source, nor one switched off.
        $reason = $this->getJson('/api/v1/business-settings/crm')->json('data.lists.lost_reason.0.id');
        $this->add(['source_id' => $reason])->assertUnprocessable()->assertJsonValidationErrors(['source_id' => 'Choose one of the lead sources in Settings → CRM.']);

        $event = $this->source('Event');
        $this->putJson("/api/v1/business-settings/crm/options/{$event}", ['name' => 'Event', 'is_active' => false])->assertOk();
        $this->add(['source_id' => $event])->assertUnprocessable()->assertJsonValidationErrors('source_id');

        // Another business's team is not one.
        $theirs = SalesTeam::create(['workspace_id' => $this->businessOf(User::factory()->create())->id, 'name' => 'Theirs']);
        $this->add(['sales_team_id' => $theirs->id])->assertUnprocessable()->assertJsonValidationErrors(['sales_team_id' => 'Choose one of the sales teams switched on.']);
    }

    public function test_assigning_a_lead_tells_the_new_owner_and_keeps_the_previous_one(): void
    {
        $dhaka   = $this->location('Dhaka');
        $ctg     = $this->location('Chattogram');
        $seller  = $this->member(['crm.leads.view'], [$dhaka]);
        $clerk   = $this->member(['contacts.view']);

        // Those who may view leads, by name, with the locations they may use.
        $this->assertContains(['id' => $seller->id, 'name' => $seller->name, 'locations' => [$dhaka]], $this->getJson('/api/v1/crm/leads/options')->json('data.owners'));
        $this->assertNotContains($clerk->id, array_column($this->getJson('/api/v1/crm/leads/options')->json('data.owners'), 'id'));

        // Left in the queue: New, with nobody to own it.
        $lead = $this->add(['owner_id' => null, 'location_id' => $dhaka])->assertCreated()->assertJsonPath('data.status', 'new')->assertJsonPath('data.owner', null)->json('data.id');

        $this->postJson("/api/v1/crm/leads/{$lead}/assign", ['owner_id' => $clerk->id])->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_id' => "Choose an active member who may view leads at this lead's location."]);

        $this->postJson("/api/v1/crm/leads/{$lead}/assign", ['owner_id' => $seller->id])->assertOk()
            ->assertJsonPath('message', "The lead is now {$seller->name}'s.")
            ->assertJsonPath('data.status', 'assigned');

        $notice = UserNotification::query()->where('user_id', $seller->id)->sole();
        $this->assertSame(['leads.assigned', "/crm/leads/{$lead}"], [$notice->trigger, $notice->link]);
        $this->assertSame("{$this->user->name} gave you a lead: Rahim Uddin", $notice->title);

        $this->postJson("/api/v1/crm/leads/{$lead}/assign", ['owner_id' => $this->user->id])->assertOk();
        $this->assertTrue(ActivityLog::query()->where('description', "Assigned lead \"Rahim Uddin\" to {$this->user->name} (was {$seller->name})")->exists());

        // Moved where its owner cannot see it, it is refused.
        $mine = $this->add(['location_id' => $dhaka])->json('data.id');
        $this->postJson("/api/v1/crm/leads/{$mine}/assign", ['owner_id' => $seller->id])->assertOk();
        $this->putJson("/api/v1/crm/leads/{$mine}", ['name' => 'Rahim Uddin', 'email' => 'rahim@example.com', 'source_id' => $this->source(), 'location_id' => $ctg])->assertUnprocessable()
            ->assertJsonValidationErrors(['location_id' => "The lead's owner may not use that location. Give the lead to someone who may first."]);

        // Without Assign Leads, a member adds leads for themselves only.
        Sanctum::actingAs($maker = $this->member(['crm.leads.view', 'crm.leads.manage']));
        $this->add(['owner_id' => $seller->id])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        $this->add()->assertCreated()->assertJsonPath('data.owner.id', $maker->id);
    }

    public function test_a_lead_goes_through_its_statuses(): void
    {
        // Its need given, as qualifying asks for it to start (Phase B2a).
        $lead = $this->add(['customer_need' => 'Shelving for a new shop'])->json('data.id');
        $step = fn (string $step, array $body = []) => $this->postJson("/api/v1/crm/leads/{$lead}/{$step}", $body);

        $step('contact')->assertOk()->assertJsonPath('data.status', 'contacted');
        $step('contact')->assertStatus(409);
        $step('qualify')->assertOk()->assertJsonPath('data.status', 'qualified');
        $step('unqualify')->assertUnprocessable()->assertJsonValidationErrors(['reason' => 'Say why the lead is unqualified.']);
        $step('unqualify', ['reason' => 'No budget this year'])->assertOk()->assertJsonPath('data.status', 'unqualified')
            ->assertJsonPath('data.status_reason', 'No budget this year')
            ->assertJsonPath('data.actions', ['archive', 'reopen']);

        // Closed, it is reopened before it changes.
        $this->putJson("/api/v1/crm/leads/{$lead}", ['name' => 'Rahim', 'email' => 'r@example.com', 'source_id' => $this->source()])->assertStatus(409);
        $step('archive')->assertOk()->assertJsonPath('data.status', 'archived');
        $this->getJson('/api/v1/crm/leads')->assertJsonPath('data', []);
        $this->getJson('/api/v1/crm/leads?status=archived')->assertJsonPath('data.0.id', $lead);

        // Reopening needs its permission.
        Sanctum::actingAs($this->member(['crm.leads.view', 'crm.leads.manage', 'crm.records.all']));
        $step('reopen')->assertForbidden();

        Sanctum::actingAs($this->user);
        $step('reopen')->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.status_reason', null);
        $this->assertTrue(ActivityLog::query()->where('description', 'Reopened lead "Rahim Uddin"')->exists());
    }

    public function test_each_member_sees_the_leads_their_role_gives_them(): void
    {
        $dhaka = $this->location('Dhaka');
        $ctg   = $this->location('Chattogram');
        $ana   = $this->member(['crm.leads.view', 'crm.leads.manage']);
        $bob   = $this->member(['crm.leads.view', 'crm.leads.manage', 'crm.records.team']);
        $cara  = $this->member(['crm.leads.view', 'crm.records.all'], [$dhaka]);
        $dan   = $this->member(['crm.leads.view', 'crm.leads.assign']);
        SalesTeam::create(['workspace_id' => $this->business->id, 'name' => 'North', 'manager_id' => $bob->id])->members()->sync([$ana->id]);

        $anas   = $this->add(['name' => 'Ana Lead', 'owner_id' => $ana->id, 'location_id' => $dhaka])->json('data.id');
        $mine   = $this->add(['name' => 'My Lead', 'location_id' => $ctg])->json('data.id');
        $queued = $this->add(['name' => 'Queued Lead', 'owner_id' => null, 'location_id' => $dhaka])->json('data.id');

        $seen = function (User $user): array {
            Sanctum::actingAs($user);

            return collect($this->getJson('/api/v1/crm/leads')->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
        };

        $this->assertSame([$anas], $seen($ana));
        $this->assertSame([$anas], $seen($bob));
        $this->assertSame([$anas, $queued], $seen($cara));
        $this->assertSame([$queued], $seen($dan));
        $this->assertSame([$anas, $mine, $queued], $seen($this->user));

        // One out of sight is not found - or, at a location out of reach, refused.
        Sanctum::actingAs($ana);
        $this->getJson("/api/v1/crm/leads/{$mine}")->assertNotFound();
        Sanctum::actingAs($cara);
        $this->getJson("/api/v1/crm/leads/{$mine}")->assertForbidden();
    }

    public function test_working_with_opportunities_only_keeps_the_leads_but_changes_none(): void
    {
        $lead = $this->add()->json('data.id');

        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'opportunities'])->assertOk();

        // Read, offering nothing to do.
        $this->getJson("/api/v1/crm/leads/{$lead}")->assertOk()->assertJsonPath('data.actions', []);
        $this->add()->assertForbidden()->assertJsonPath('message', 'This business works with opportunities only, so leads are not added or changed. Settings → CRM can bring them back.');
        $this->postJson("/api/v1/crm/leads/{$lead}/contact")->assertForbidden();
    }

    public function test_a_source_or_a_team_a_lead_uses_stays(): void
    {
        $source = $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'lead_source', 'name' => 'Trade Fair'])->assertCreated()->json('data.id');
        $team   = $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'North', 'member_ids' => []])->assertCreated()->json('data.id');

        $this->add(['source_id' => $source, 'sales_team_id' => $team])->assertCreated()->assertJsonPath('data.team.name', 'North');

        $this->deleteJson("/api/v1/business-settings/crm/options/{$source}")->assertStatus(409)
            ->assertJsonPath('message', 'Leads or opportunities came from this source, so it stays. Switch it off instead.');
        $this->deleteJson("/api/v1/business-settings/sales-teams/{$team}")->assertUnprocessable()
            ->assertJsonValidationErrors(['team' => 'Sales documents or leads name this team, so it stays: switch it off instead.']);
        $this->assertSame(1, CrmLead::query()->count());
    }
}
