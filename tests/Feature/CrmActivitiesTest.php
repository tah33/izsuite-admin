<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase E1: activities - planned for a lead, an opportunity or a
 * contact, given to a member and due at a day and time in the business's
 * time zone (Dhaka, six hours ahead of UTC); completed with an outcome where
 * the type needs one and perhaps a follow-up, or cancelled; on the
 * Activities page and on their record's. Monday 16 November 2026, 10:00
 * UTC - 16:00 in Dhaka.
 */
class CrmActivitiesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $contact;

    /** @var array<string, int> the activity types by key */
    private array $types;

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
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Main Business', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Road 1', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo',
        ]);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->contact     = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $this->types       = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->pluck('id', 'key')->all();
        $stage             = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $this->opportunity = $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - racking', 'contact_id' => $this->contact->id, 'stage_id' => $stage, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20',
        ])->assertCreated()->json('data.id');
    }

    /** An activity for the opportunity, a call due today at 15:00 in Dhaka unless $fields say otherwise. */
    private function plan(array $fields = []): TestResponse
    {
        return $this->postJson('/api/v1/crm/activities', [
            'opportunity_id' => $this->opportunity, 'type_id' => $this->types['call'], 'subject' => 'Call about the racking', 'due_on' => '2026-11-16', 'due_time' => '15:00', ...$fields,
        ]);
    }

    private function names(string $query = ''): array
    {
        return array_column($this->getJson('/api/v1/crm/activities'.$query)->assertOk()->json('data'), 'subject');
    }

    public function test_an_activity_is_planned_for_a_record_in_the_business_time_and_listed_by_it(): void
    {
        $options = $this->getJson('/api/v1/crm/activities/options')->assertOk()->json('data');
        $this->assertSame(['call', 'meeting', 'task', 'email', 'follow_up', 'demonstration', 'site_visit', 'other'], array_column($options['types'], 'key'));
        $this->assertSame([true, true, false, false, false, true, true, false], array_column($options['types'], 'needs_outcome'));
        $this->assertSame([['lead', 'opportunity', 'contact'], $this->user->id], [$options['kinds'], $options['user_id']]);

        // 15:00 in Dhaka is 09:00 UTC: an hour ago.
        $call = $this->plan(['call_direction' => 'outbound', 'duration_minutes' => 15])->assertCreated()
            ->assertJsonPath('message', 'Activity planned.')
            ->assertJsonPath('data.regarding', ['kind' => 'opportunity', 'id' => $this->opportunity, 'name' => 'Uddin Traders - racking'])
            ->assertJsonPath('data.assigned_to', ['id' => $this->user->id, 'name' => $this->user->name])
            ->json('data');
        $this->assertSame(['2026-11-16', '15:00', true, 'outbound', 15, 'planned'], [$call['due_on'], $call['due_time'], $call['overdue'], $call['call_direction'], $call['duration_minutes'], $call['status']]);

        $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Karim Ahmed', 'phone' => '+8801711000000', 'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id')])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/crm/activities', ['lead_id' => $lead, 'type_id' => $this->types['task'], 'subject' => 'Send the brochure', 'due_on' => '2026-11-17', 'due_time' => '10:00', 'call_direction' => 'inbound'])->assertCreated()
            ->assertJsonPath('data.call_direction', null)
            ->assertJsonPath('data.overdue', false);
        $this->postJson('/api/v1/crm/activities', ['contact_id' => $this->contact->id, 'type_id' => $this->types['meeting'], 'subject' => 'Visit the shop', 'due_on' => '2026-11-16', 'due_time' => '18:00', 'place' => 'Uddin Traders, Mirpur'])->assertCreated();

        // For exactly one record of the business's, of a type switched on.
        $this->postJson('/api/v1/crm/activities', ['type_id' => $this->types['task'], 'subject' => 'X', 'due_on' => '2026-11-17', 'due_time' => '10:00'])->assertUnprocessable()->assertJsonValidationErrors('regarding');
        $this->plan(['contact_id' => $this->contact->id])->assertUnprocessable()->assertJsonValidationErrors('regarding');
        $this->plan(['type_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('type_id');
        $theirs = $this->businessOf(User::factory()->create())->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Theirs']);
        $this->plan(['opportunity_id' => null, 'contact_id' => $theirs->id])->assertNotFound();

        // Planned, the soonest due first; today's in Dhaka; the overdue.
        $this->assertSame(['Call about the racking', 'Visit the shop', 'Send the brochure'], $this->names());
        $this->assertSame(['Call about the racking', 'Visit the shop'], $this->names('?status=today'));
        $this->assertSame(['Call about the racking'], $this->names('?status=overdue'));
        $this->assertSame(['Visit the shop'], $this->names('?search=Uddin&type_id='.$this->types['meeting']));
        $this->assertSame(['Call about the racking'], $this->names('?search=racking'));

        // On its record's page, and logged on the record.
        $this->assertSame(['Call about the racking'], array_column($this->getJson("/api/v1/crm/activities/for/opportunity/{$this->opportunity}")->assertOk()->json('data'), 'subject'));
        $this->assertTrue(ActivityLog::query()->where('model_type', CrmOpportunity::class)->where('model_id', $this->opportunity)->where('description', 'Planned Call "Call about the racking"')->exists());

        $this->assertSame(['Uddin Traders - racking'], array_column($this->getJson('/api/v1/crm/activities/regarding?kind=opportunity&search=racking')->assertOk()->json('data'), 'name'));
    }

    public function test_completing_needs_an_outcome_where_its_type_does_and_can_plan_a_follow_up(): void
    {
        $call = $this->plan()->json('data.id');

        $this->postJson("/api/v1/crm/activities/{$call}/complete")->assertUnprocessable()
            ->assertJsonValidationErrors(['outcome' => 'A Call needs its outcome once it is done.']);

        $this->postJson("/api/v1/crm/activities/{$call}/complete", ['outcome' => 'They want a quotation by Friday.', 'follow_up' => [
            'type_id' => $this->types['task'], 'subject' => 'Send the quotation', 'due_on' => '2026-11-18', 'due_time' => '11:00',
        ]])->assertOk()
            ->assertJsonPath('message', 'Activity completed, and its follow-up planned.')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.outcome', 'They want a quotation by Friday.')
            ->assertJsonPath('data.completed_by', $this->user->name)
            ->assertJsonPath('data.overdue', false);

        $this->assertSame([['Send the quotation', 'planned'], ['Call about the racking', 'completed']], array_map(
            fn (array $row) => [$row['subject'], $row['status']],
            $this->getJson("/api/v1/crm/activities/for/opportunity/{$this->opportunity}")->json('data'),
        ));
        $this->assertSame(['Call about the racking'], $this->names('?status=completed'));

        // Done once, for good.
        $this->postJson("/api/v1/crm/activities/{$call}/complete", ['outcome' => 'Again'])->assertStatus(409)->assertJsonPath('message', 'A completed or cancelled activity stays as it is.');
        $this->putJson("/api/v1/crm/activities/{$call}", ['type_id' => $this->types['call'], 'subject' => 'X', 'due_on' => '2026-11-16', 'due_time' => '15:00'])->assertStatus(409);

        // A task needs none; cancelling is final too.
        $task = $this->plan(['type_id' => $this->types['task'], 'subject' => 'Check the stock'])->json('data.id');
        $this->postJson("/api/v1/crm/activities/{$task}/complete")->assertOk()->assertJsonPath('message', 'Activity completed.');
        $meeting = $this->plan(['type_id' => $this->types['meeting'], 'subject' => 'Meet at the site'])->json('data.id');
        $this->postJson("/api/v1/crm/activities/{$meeting}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancelled_by', $this->user->name);
        $this->postJson("/api/v1/crm/activities/{$meeting}/cancel")->assertStatus(409);

        // Settings > CRM: a call needs no outcome once switched so; a type in use stays.
        $this->putJson("/api/v1/business-settings/crm/options/{$this->types['call']}", ['name' => 'Call', 'needs_outcome' => false])->assertOk()->assertJsonPath('data.needs_outcome', false);
        $this->postJson('/api/v1/crm/activities/'.$this->plan()->json('data.id').'/complete')->assertOk();

        $visit = $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'activity_type', 'name' => 'Showroom Visit', 'needs_outcome' => true])->assertCreated()->assertJsonPath('data.needs_outcome', true)->json('data.id');
        $this->plan(['type_id' => $visit]);
        $this->deleteJson("/api/v1/business-settings/crm/options/{$visit}")->assertStatus(409)->assertJsonPath('message', 'Activities are of this type, so it stays. Switch it off instead.');
    }

    public function test_a_planned_activity_is_changed_and_given_to_whoever_may_view_activities(): void
    {
        $call = $this->plan()->json('data.id');
        $seller = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Seller', 'permissions' => ['crm.activities.view', 'crm.activities.manage']]);
        $this->business->members()->create(['user_id' => $seller->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        $this->putJson("/api/v1/crm/activities/{$call}", ['type_id' => $this->types['call'], 'subject' => 'Call Rahim about the racking', 'due_on' => '2026-11-19', 'due_time' => '09:30', 'assigned_to' => $seller->id])->assertOk()
            ->assertJsonPath('message', 'Activity saved.')
            ->assertJsonPath('data.assigned_to.id', $seller->id)
            ->assertJsonPath('data.due_time', '09:30')
            ->assertJsonPath('data.overdue', false);
        // On the record's timeline, read as a person reads them: Dhaka's time, the assignee by name.
        $changes = ActivityLog::query()->where('model_id', $this->opportunity)->where('description', 'Updated Call "Call Rahim about the racking"')->sole()->properties['changes'];
        $this->assertContains(['setting' => 'due_at', 'from' => '2026-11-16 15:00', 'to' => '2026-11-19 09:30'], $changes);
        $this->assertContains(['setting' => 'assigned_to', 'from' => $this->user->name, 'to' => $seller->name], $changes);

        $this->putJson("/api/v1/crm/activities/{$call}", ['type_id' => $this->types['call'], 'subject' => 'X', 'due_on' => '2026-11-19', 'due_time' => '09:30', 'assigned_to' => User::factory()->create()->id])
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $this->putJson("/api/v1/crm/activities/{$call}", ['type_id' => $this->types['call'], 'subject' => 'X', 'due_on' => '2026-11-19', 'due_time' => '25:00', 'contact_id' => $this->contact->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['due_time', 'contact_id']);
    }

    public function test_whom_an_activity_is_given_to_sees_it_and_its_record_shows_it_to_whoever_sees_the_record(): void
    {
        $member = function (array $permissions): User {
            $user = User::factory()->create();
            $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
            $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

            return $user;
        };
        $ana  = $member(['crm.activities.view', 'crm.activities.manage', 'crm.opportunities.view']);
        $bob  = $member(['crm.activities.view', 'crm.activities.manage', 'crm.opportunities.view']);
        $cara = $member(['crm.activities.view', 'crm.records.all', 'crm.opportunities.view']);

        $forAna = $this->plan(['assigned_to' => $ana->id, 'subject' => "Ana's call"])->json('data.id');
        $this->plan(['subject' => 'My call']);

        // Ana sees and completes hers, though not the opportunity (it is the owner's).
        Sanctum::actingAs($ana);
        $this->assertSame(["Ana's call"], $this->names());
        $this->getJson("/api/v1/crm/activities/for/opportunity/{$this->opportunity}")->assertNotFound();
        $this->postJson("/api/v1/crm/activities/{$forAna}/complete", ['outcome' => 'Spoke to Rahim.'])->assertOk();

        // Bob sees none of them; Cara sees every one.
        Sanctum::actingAs($bob);
        $this->assertSame([], $this->names('?status=all'));
        $this->postJson("/api/v1/crm/activities/{$forAna}/cancel")->assertNotFound();

        Sanctum::actingAs($cara);
        $this->assertSame(['My call', "Ana's call"], $this->names('?status=all'));
        $this->assertSame(['My call'], $this->names('?assigned_to='.$this->user->id));
        $this->postJson('/api/v1/crm/activities', ['contact_id' => $this->contact->id, 'type_id' => $this->types['task'], 'subject' => 'X', 'due_on' => '2026-11-17', 'due_time' => '10:00'])->assertForbidden();
    }

    public function test_a_leads_activities_go_with_it_into_its_opportunity_and_a_contacts_stay_with_it(): void
    {
        $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Karim Ahmed', 'business_name' => 'Karim Stores', 'phone' => '+8801711000000', 'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id'), 'customer_need' => 'Shelving'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/crm/activities', ['lead_id' => $lead, 'type_id' => $this->types['task'], 'subject' => 'Measure the shop', 'due_on' => '2026-11-17', 'due_time' => '10:00'])->assertCreated();
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();

        $stage       = $this->getJson("/api/v1/crm/leads/{$lead}/conversion")->json('data.defaults.stage_id');
        $opportunity = $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => null, 'opportunity' => ['name' => 'Karim Stores - shelving', 'stage_id' => $stage, 'expected_value' => '900', 'currency' => 'USD', 'expected_close_date' => '2026-12-31']])
            ->assertOk()->json('data.conversion.opportunity.id');

        $this->getJson("/api/v1/crm/activities/for/opportunity/{$opportunity}")->assertJsonPath('data.0.subject', 'Measure the shop')->assertJsonPath('data.0.regarding.kind', 'opportunity');
        $this->getJson("/api/v1/crm/activities/for/lead/{$lead}")->assertJsonPath('data.0.subject', 'Measure the shop');

        // A contact with activities is deactivated, not deleted; merged, they move.
        $other = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Trading']);
        $this->postJson('/api/v1/crm/activities', ['contact_id' => $other->id, 'type_id' => $this->types['email'], 'subject' => 'Send the price list', 'due_on' => '2026-11-17', 'due_time' => '10:00'])->assertCreated();
        $this->deleteJson("/api/v1/contacts/{$other->id}")->assertStatus(409)->assertJsonPath('message', 'This contact has CRM activities, so it cannot be deleted. Deactivate it instead.');

        $this->getJson("/api/v1/contacts/{$this->contact->id}/merge?merged_id={$other->id}")->assertOk()->assertJsonPath('data.moving.activities', 1);
        $this->postJson("/api/v1/contacts/{$this->contact->id}/merge", ['merged_id' => $other->id])->assertOk();
        $this->assertSame(['Send the price list'], array_column($this->getJson("/api/v1/crm/activities/for/contact/{$this->contact->id}")->json('data'), 'subject'));
        $this->postJson('/api/v1/crm/activities', ['contact_id' => $other->id, 'type_id' => $this->types['task'], 'subject' => 'X', 'due_on' => '2026-11-17', 'due_time' => '10:00'])->assertStatus(409);
    }
}
