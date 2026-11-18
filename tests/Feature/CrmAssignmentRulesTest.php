<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\CrmAssignmentRule;
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
 * Doc 17 Phase I1: the lead assignment rules (section 17.9) - kept in
 * Settings → CRM in their order, with their own permission (17.30) - and a
 * lead added without an owner given by the first that fits: to a person, or
 * to a sales team's members by turns or by workload, each an active member
 * who may view leads at the lead's location; none fits, the queue.
 */
class CrmAssignmentRulesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const RULES = '/api/v1/business-settings/crm/assignment-rules';

    private User $user;

    private Workspace $business;

    private User $ana;

    private User $bob;

    private int $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Main Business', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Road 1', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo',
        ]);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->ana  = $this->member('Ana', 'Ahmed', ['crm.leads.view', 'crm.leads.manage']);
        $this->bob  = $this->member('Bob', 'Biswas', ['crm.leads.view', 'crm.leads.manage']);
        $this->team = $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'Wholesale Desk', 'is_active' => true, 'member_ids' => [$this->ana->id, $this->bob->id]])->assertCreated()->json('data.id');
    }

    /** A member called $first $last whose role holds $permissions - at $locations only, when given. */
    private function member(string $first, string $last, array $permissions, ?array $locations = null): User
    {
        $user   = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
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

    private function source(string $name): int
    {
        return collect($this->getJson('/api/v1/crm/leads/options')->json('data.sources'))->firstWhere('name', $name)['id'];
    }

    private function rule(array $fields): int
    {
        return $this->postJson(self::RULES, $fields)->assertCreated()->json('data.id');
    }

    /** A lead from the website added without an owner, unless $fields gives one: the lead as the API answers. */
    private function lead(string $name, array $fields = []): array
    {
        return $this->postJson('/api/v1/crm/leads', ['name' => $name, 'phone' => '+880 1711-000000', 'source_id' => $this->source('Website'), 'owner_id' => null, ...$fields])->assertCreated()->json('data');
    }

    public function test_rules_are_kept_in_settings_in_their_order(): void
    {
        $index = $this->getJson(self::RULES)->assertOk()->json('data');
        $this->assertSame([], $index['rules']);
        $this->assertEqualsCanonicalizing(['Ana Ahmed', 'Bob Biswas', $this->user->name], array_column($index['owners'], 'name'));
        $this->assertSame(['Wholesale Desk'], array_column($index['teams'], 'name'));

        $web  = $this->rule(['name' => 'Website to Ana', 'source_id' => $this->source('Website'), 'user_id' => $this->ana->id]);
        $desk = $this->postJson(self::RULES, ['name' => 'The desk', 'country' => 'BD', 'team_id' => $this->team, 'method' => 'workload'])->assertCreated()
            ->assertJsonPath('message', 'Rule added.')
            ->assertJsonPath('data.team', ['id' => $this->team, 'name' => 'Wholesale Desk'])
            ->assertJsonPath('data.method', 'workload')
            ->assertJsonPath('data.user', null)
            ->json('data.id');

        // Refused: nobody to give leads to, both at once, a team without its method, a name taken, what is not a lead source, someone who may not own leads.
        $this->postJson(self::RULES, ['name' => 'Nobody'])->assertUnprocessable()->assertJsonValidationErrors(['user_id', 'team_id']);
        $this->postJson(self::RULES, ['name' => 'Both', 'user_id' => $this->ana->id, 'team_id' => $this->team, 'method' => 'round_robin'])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson(self::RULES, ['name' => 'No method', 'team_id' => $this->team])->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->postJson(self::RULES, ['name' => 'Website to Ana', 'user_id' => $this->bob->id])->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Another rule has this name.']);
        $call = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->firstWhere('key', 'call')['id'];
        $this->postJson(self::RULES, ['name' => 'Calls', 'source_id' => $call, 'user_id' => $this->ana->id])->assertUnprocessable()->assertJsonValidationErrors(['source_id' => 'Choose one of your lead sources.']);
        $viewer = $this->member('Cara', 'Chowdhury', ['contacts.view']);
        $this->postJson(self::RULES, ['name' => 'Cara', 'user_id' => $viewer->id])->assertUnprocessable()->assertJsonValidationErrors(['user_id' => 'Choose an active member who may view leads.']);

        // Changed and switched off, put in another order - every rule, once each - and deleted.
        $this->putJson(self::RULES."/{$web}", ['name' => 'Website leads to Ana', 'source_id' => $this->source('Website'), 'user_id' => $this->ana->id, 'is_active' => false])->assertOk()
            ->assertJsonPath('message', 'Rule saved.')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.source.name', 'Website');
        $this->putJson(self::RULES.'/order', ['ids' => [$desk, $web]])->assertOk()->assertJsonPath('data.0.name', 'The desk');
        $this->putJson(self::RULES.'/order', ['ids' => [$desk]])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->assertSame(['The desk', 'Website leads to Ana'], array_column($this->getJson(self::RULES)->json('data.rules'), 'name'));
        $this->deleteJson(self::RULES."/{$web}")->assertOk()->assertJsonPath('message', 'Rule deleted.');
        $this->assertTrue(ActivityLog::query()->where('description', 'Deleted the assignment rule "Website leads to Ana"')->exists());

        // Manage Assignment and Scoring Rules is its own permission.
        Sanctum::actingAs($this->member('Dina', 'Das', ['settings.view', 'crm.settings.manage']));
        $this->getJson(self::RULES)->assertForbidden();
        Sanctum::actingAs($this->member('Emon', 'Ali', ['settings.view', 'crm.rules.manage']));
        $this->getJson(self::RULES)->assertOk()->assertJsonCount(1, 'data.rules');
    }

    public function test_a_lead_left_without_an_owner_goes_to_the_first_rule_that_fits(): void
    {
        $racking = $this->postJson('/api/v1/items', ['type' => 'physical', 'usage' => 'both', 'name' => 'Racking', 'unit' => 'Piece'])->assertCreated()->json('data.id');
        $this->rule(['name' => 'Referrals to Bob', 'source_id' => $this->source('Referral'), 'user_id' => $this->bob->id]);
        $this->rule(['name' => 'Bangladesh to the desk', 'country' => 'BD', 'team_id' => $this->team, 'method' => 'round_robin']);
        $this->rule(['name' => 'Racking to Ana', 'item_id' => $racking, 'user_id' => $this->ana->id]);

        $referred = $this->lead('Referred', ['source_id' => $this->source('Referral'), 'address_country' => 'BD']);
        $this->assertSame(['Bob Biswas', 'assigned'], [$referred['owner']['name'], $referred['status']]);
        $this->assertTrue(ActivityLog::query()->where('description', 'Added lead "Referred" for Bob Biswas by the assignment rule "Referrals to Bob"')->exists());

        // The desk by turns, in name order.
        $this->assertSame(['Ana Ahmed', 'Bob Biswas', 'Ana Ahmed'], array_map(fn (string $name) => $this->lead($name, ['address_country' => 'BD'])['owner']['name'], ['First', 'Second', 'Third']));
        $this->assertSame('Ana Ahmed', $this->lead('Racking', ['address_country' => 'IN', 'interested_items' => [['item_id' => $racking, 'quantity' => 2]]])['owner']['name']);

        // None fits: the queue. An owner chosen: no rule asked.
        $queued = $this->lead('Elsewhere', ['address_country' => 'IN']);
        $this->assertSame([null, 'new'], [$queued['owner'], $queued['status']]);
        $this->assertSame($this->user->name, $this->lead('Mine', ['address_country' => 'BD', 'owner_id' => $this->user->id])['owner']['name']);

        // Switched off, a rule is passed over; the desk's turn goes on after Ana.
        CrmAssignmentRule::query()->where('name', 'Referrals to Bob')->update(['is_active' => false]);
        $this->assertSame('Bob Biswas', $this->lead('Referred again', ['source_id' => $this->source('Referral'), 'address_country' => 'BD'])['owner']['name']);
    }

    public function test_workload_the_lead_s_location_and_sending_a_queued_lead_through_the_rules(): void
    {
        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK'])->id;
        $ctg   = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG'])->id;
        $cara  = $this->member('Cara', 'Chowdhury', ['crm.leads.view', 'crm.leads.manage'], [$dhaka]);

        $queued = $this->lead('Waiting', ['location_id' => $dhaka]);
        $this->assertNull($queued['owner']);
        $this->postJson("/api/v1/crm/leads/{$queued['id']}/assign-by-rules")->assertUnprocessable()->assertJsonValidationErrors(['owner_id' => 'No assignment rule fits this lead. Assign it by hand.']);

        // Cara may not work at Chattogram, so her rule never fits there; the desk shares by workload.
        $this->rule(['name' => 'Chattogram to Cara', 'location_id' => $ctg, 'user_id' => $cara->id]);
        $this->rule(['name' => 'The desk by workload', 'team_id' => $this->team, 'method' => 'workload']);
        $this->lead('Ana 1', ['owner_id' => $this->ana->id, 'location_id' => $dhaka]);
        $this->lead('Ana 2', ['owner_id' => $this->ana->id, 'location_id' => $dhaka]);

        // Ana has two open, Bob none: Bob twice, then by turns between equals.
        $this->assertSame(['Bob Biswas', 'Bob Biswas', 'Ana Ahmed'], array_map(fn (string $name) => $this->lead($name, ['location_id' => $ctg])['owner']['name'], ['One', 'Two', 'Three']));
        $this->assertTrue(ActivityLog::query()->where('description', 'Added lead "One" for Bob Biswas by the assignment rule "The desk by workload"')->exists());

        // Sent through the rules now, the queued lead goes to Bob: Ana has three open, Bob two.
        $this->postJson("/api/v1/crm/leads/{$queued['id']}/assign-by-rules")->assertOk()
            ->assertJsonPath('message', "The lead is now Bob Biswas's, by the assignment rules.")
            ->assertJsonPath('data.owner.name', 'Bob Biswas')
            ->assertJsonPath('data.status', 'assigned');
        $this->assertTrue(ActivityLog::query()->where('description', 'Assigned lead "Waiting" to Bob Biswas by the assignment rule "The desk by workload"')->exists());
        $this->postJson("/api/v1/crm/leads/{$queued['id']}/assign-by-rules")->assertUnprocessable()->assertJsonValidationErrors(['owner_id' => 'Only a lead in the queue goes to the assignment rules.']);

        // At Dhaka, where Cara may work, her rule does not ask - it is Chattogram's - and the desk is level, three each: Ana's turn after Bob.
        $this->assertSame('Ana Ahmed', $this->lead('In Dhaka', ['location_id' => $dhaka])['owner']['name']);

        // A team switched off gives no leads: the queue.
        $this->putJson("/api/v1/business-settings/sales-teams/{$this->team}", ['name' => 'Wholesale Desk', 'is_active' => false, 'member_ids' => [$this->ana->id, $this->bob->id]])->assertOk();
        $this->assertNull($this->lead('Desk closed', ['location_id' => $ctg])['owner']);
    }
}
