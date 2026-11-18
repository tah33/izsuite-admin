<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase G3a: response and follow-up (section 17.23) on a lead's and
 * an opportunity's page - when a lead was first given an owner and first
 * answered, and how long that took; the last contact and how; the next
 * activity, overdue or not; and the full days without activity while open.
 * Wednesday 18 November 2026, 06:00 UTC - 12:00 in Dhaka.
 */
class CrmFollowUpTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $contact;

    private int $source;

    /** @var array<string, int> the activity types by key */
    private array $types;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Main Business', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Road 1', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo',
        ]);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->contact = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $this->source  = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');
        $this->types   = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->pluck('id', 'key')->all();
    }

    private function lead(array $fields = []): int
    {
        return $this->postJson('/api/v1/crm/leads', ['name' => 'Karim Ahmed', 'email' => 'karim@stores.test', 'source_id' => $this->source, ...$fields])->assertCreated()->json('data.id');
    }

    private function opportunity(): int
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - racking', 'contact_id' => $this->contact->id, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20',
            'stage_id' => $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id'),
        ])->assertCreated()->json('data.id');
    }

    /** An activity for $for (["lead_id" => 1], say), due at $on and $at in Dhaka. */
    private function plan(array $for, string $type, string $subject, string $on, string $at): int
    {
        return $this->postJson('/api/v1/crm/activities', [...$for, 'type_id' => $this->types[$type], 'subject' => $subject, 'due_on' => $on, 'due_time' => $at])->assertCreated()->json('data.id');
    }

    private function email(array $from, string $to): void
    {
        $this->postJson('/api/v1/crm/emails', [...$from, 'to' => $to, 'subject' => 'Your shelving', 'body' => 'Dear Karim, here is what we spoke about.'])->assertCreated();
    }

    private function followUp(string $kind, int $id): array
    {
        return $this->getJson("/api/v1/crm/{$kind}/{$id}")->assertOk()->json('data.follow_up');
    }

    /** A member of the business whose role holds $permissions, at every location. */
    private function member(array $permissions): User
    {
        $user = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Islam']);
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_a_lead_keeps_when_it_was_first_assigned_and_first_answered(): void
    {
        $seller = $this->member(['crm.leads.view', 'crm.leads.manage']);
        $lead   = $this->lead(['owner_id' => null]);

        $this->assertSame(
            ['first_assigned_at' => null, 'first_contacted_at' => null, 'response_seconds' => null, 'last_contact' => null, 'next_activity' => null, 'days_without_activity' => 0],
            $this->followUp('leads', $lead),
        );

        // Given to the seller three hours on, then back to the user the next day: its first assignment stays.
        $this->travel(3)->hours();
        $this->postJson("/api/v1/crm/leads/{$lead}/assign", ['owner_id' => $seller->id])->assertOk();
        $this->travel(1)->days();
        $this->postJson("/api/v1/crm/leads/{$lead}/assign", ['owner_id' => $this->user->id])->assertOk()
            ->assertJsonPath('data.follow_up.first_assigned_at', '2026-11-18T09:00:00+00:00')
            ->assertJsonPath('data.assigned_at', '2026-11-19T09:00:00+00:00');

        // A call planned for Friday 10:00 in Dhaka (04:00 UTC): its next activity, overdue once that has passed.
        $call = $this->plan(['lead_id' => $lead], 'call', 'Talk about the shelving', '2026-11-20', '10:00');
        $this->assertSame(['at' => '2026-11-20T04:00:00+00:00', 'overdue' => false, 'label' => 'Call "Talk about the shelving"'], $this->followUp('leads', $lead)['next_activity']);
        $this->travel(1)->days();
        $this->assertTrue($this->followUp('leads', $lead)['next_activity']['overdue']);

        // Done: its first contact, two days and three hours after it was added - the lead's version kept, so an open edit still saves.
        $version = $this->getJson("/api/v1/crm/leads/{$lead}")->json('data.version');
        $this->postJson("/api/v1/crm/activities/{$call}/complete", ['outcome' => 'Wants two racks'])->assertOk();
        $answered = $this->getJson("/api/v1/crm/leads/{$lead}")->assertJsonPath('data.version', $version)->json('data.follow_up');
        $this->assertSame(
            ['2026-11-20T09:00:00+00:00', 183600, ['at' => '2026-11-20T09:00:00+00:00', 'by' => 'activity'], null, 0],
            [$answered['first_contacted_at'], $answered['response_seconds'], $answered['last_contact'], $answered['next_activity'], $answered['days_without_activity']],
        );

        // Marked contacted the next day: its last contact, its first kept.
        $this->travel(1)->days();
        $this->postJson("/api/v1/crm/leads/{$lead}/contact")->assertOk()
            ->assertJsonPath('data.follow_up.first_contacted_at', '2026-11-20T09:00:00+00:00')
            ->assertJsonPath('data.follow_up.last_contact', ['at' => '2026-11-21T09:00:00+00:00', 'by' => 'marked']);

        // Three days and five hours on: three full days without activity - and none counted once archived.
        $this->travel(3)->days();
        $this->travel(5)->hours();
        $this->assertSame(3, $this->followUp('leads', $lead)['days_without_activity']);
        $this->postJson("/api/v1/crm/leads/{$lead}/archive")->assertOk()->assertJsonPath('data.follow_up.days_without_activity', null);
    }

    public function test_a_lead_added_with_an_owner_and_e_mailed_is_answered_from_the_moment_it_is_sent(): void
    {
        $lead    = $this->lead();
        $version = $this->getJson("/api/v1/crm/leads/{$lead}")
            ->assertJsonPath('data.follow_up.first_assigned_at', '2026-11-18T06:00:00+00:00')
            ->json('data.version');

        $this->travel(45)->minutes();
        $this->email(['lead_id' => $lead], 'karim@stores.test');

        $followUp = $this->getJson("/api/v1/crm/leads/{$lead}")->assertJsonPath('data.version', $version)->json('data.follow_up');
        $this->assertSame(
            ['2026-11-18T06:45:00+00:00', 2700, ['at' => '2026-11-18T06:45:00+00:00', 'by' => 'email']],
            [$followUp['first_contacted_at'], $followUp['response_seconds'], $followUp['last_contact']],
        );

        // A later activity done is its last contact; its first stays.
        $this->travel(2)->hours();
        $task = $this->plan(['lead_id' => $lead], 'task', 'Send the price list', '2026-11-18', '15:00');
        $this->postJson("/api/v1/crm/activities/{$task}/complete")->assertOk();
        $this->assertSame(['2026-11-18T06:45:00+00:00', 'activity'], [$this->followUp('leads', $lead)['first_contacted_at'], $this->followUp('leads', $lead)['last_contact']['by']]);
    }

    public function test_an_opportunity_shows_its_last_contact_next_activity_and_days_without_activity(): void
    {
        $opportunity = $this->opportunity();
        $this->assertSame(['last_contact' => null, 'next_activity' => null, 'days_without_activity' => 0], $this->followUp('opportunities', $opportunity));

        // A call due at 10:00 in Dhaka today (04:00 UTC) is overdue already, and comes before next week's task.
        $this->plan(['opportunity_id' => $opportunity], 'task', 'Send the brochure', '2026-11-25', '10:00');
        $this->plan(['opportunity_id' => $opportunity], 'call', 'Call Rahim', '2026-11-18', '10:00');
        $this->assertSame(['at' => '2026-11-18T04:00:00+00:00', 'overdue' => true, 'label' => 'Call "Call Rahim"'], $this->followUp('opportunities', $opportunity)['next_activity']);

        // E-mailed two days on: its last contact.
        $this->travel(2)->days();
        $this->email(['opportunity_id' => $opportunity], 'rahim@uddin.test');
        $this->assertSame([['at' => '2026-11-20T06:00:00+00:00', 'by' => 'email'], 0], array_values(array_intersect_key($this->followUp('opportunities', $opportunity), array_flip(['last_contact', 'days_without_activity']))));

        // Five days and seven hours on: five full days without activity.
        $this->travel(5)->days();
        $this->travel(7)->hours();
        $this->assertSame(5, $this->followUp('opportunities', $opportunity)['days_without_activity']);

        // Whoever may not view activities sees when the next one is, not what it is.
        Sanctum::actingAs($this->member(['crm.opportunities.view', 'crm.records.all']));
        $this->assertSame(['at' => '2026-11-18T04:00:00+00:00', 'overdue' => true, 'label' => null], $this->followUp('opportunities', $opportunity)['next_activity']);

        // Won, it counts no days.
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/crm/opportunities/{$opportunity}/win", ['closed_on' => '2026-11-25'])->assertOk()->assertJsonPath('data.follow_up.days_without_activity', null);
    }

    public function test_leads_that_exist_get_their_first_assignment_and_contact_from_what_they_kept(): void
    {
        $seller = $this->member(['crm.leads.view', 'crm.leads.manage']);

        // At 06:00: one in the queue, one assigned once, one added with its owner, one added with an owner and given to another later.
        $queued    = $this->lead(['owner_id' => null, 'name' => 'Queued']);
        $once      = $this->lead(['owner_id' => null, 'name' => 'Assigned once']);
        $added     = $this->lead(['name' => 'Added with an owner']);
        $reassigned = $this->lead(['name' => 'Given to another']);

        $this->travel(1)->hours();
        $this->postJson("/api/v1/crm/leads/{$queued}/assign", ['owner_id' => $seller->id])->assertOk();
        $this->postJson("/api/v1/crm/leads/{$once}/assign", ['owner_id' => $seller->id])->assertOk();
        $this->travel(1)->hours();
        $this->postJson("/api/v1/crm/leads/{$queued}/assign", ['owner_id' => $this->user->id])->assertOk();
        $this->postJson("/api/v1/crm/leads/{$reassigned}/assign", ['owner_id' => $seller->id])->assertOk();

        // At 10:00 an activity done for one and an e-mail to another; at 11:00 two marked contacted.
        $this->travel(2)->hours();
        $this->postJson('/api/v1/crm/activities/'.$this->plan(['lead_id' => $queued], 'task', 'Measure the shop', '2026-11-18', '17:00').'/complete')->assertOk();
        $this->email(['lead_id' => $added], 'karim@stores.test');
        $this->travel(1)->hours();
        $this->postJson("/api/v1/crm/leads/{$queued}/contact")->assertOk();
        $this->postJson("/api/v1/crm/leads/{$reassigned}/contact")->assertOk();

        // Before Phase G3a: the migration again.
        $migration = require database_path('migrations/2025_01_01_000181_add_crm_lead_response_tracking.php');
        $migration->down();
        $migration->up();

        $kept = fn (int $id) => array_map(fn ($at) => $at?->toDateTimeString(), CrmLead::query()->findOrFail($id)->only(['first_assigned_at', 'first_contacted_at']));

        $this->assertSame(['first_assigned_at' => '2026-11-18 07:00:00', 'first_contacted_at' => '2026-11-18 10:00:00'], $kept($queued));
        $this->assertSame(['first_assigned_at' => '2026-11-18 07:00:00', 'first_contacted_at' => null], $kept($once));
        $this->assertSame(['first_assigned_at' => '2026-11-18 06:00:00', 'first_contacted_at' => '2026-11-18 10:00:00'], $kept($added));
        $this->assertSame(['first_assigned_at' => '2026-11-18 06:00:00', 'first_contacted_at' => '2026-11-18 11:00:00'], $kept($reassigned));
    }
}
