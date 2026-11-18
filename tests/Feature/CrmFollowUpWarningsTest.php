<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\CrmStage;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase G3b: the follow-up warnings (section 17.23) on the CRM
 * Overview - leads in the queue and not contacted in time; open
 * opportunities with no activity planned, stale, and closing soon without
 * recent activity - each listed by the Leads or Opportunities page's matching
 * status, with their periods in Settings → CRM. Wednesday 18 November 2026,
 * 06:00 UTC - 12:00 in Dhaka.
 */
class CrmFollowUpWarningsTest extends TestCase
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
        $this->stages  = collect($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages'))->pluck('id', 'name')->all();
    }

    private function overview(array $query = []): TestResponse
    {
        return $this->getJson('/api/v1/crm/overview?'.http_build_query($query))->assertOk();
    }

    /** The names a list shows: $kind is leads or opportunities. */
    private function names(string $kind, array $query): array
    {
        return array_column($this->getJson("/api/v1/crm/{$kind}?".http_build_query($query))->assertOk()->json('data'), 'name');
    }

    /** A lead added at $at (UTC). */
    private function lead(string $name, string $at, array $fields = []): int
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');

        return $this->postJson('/api/v1/crm/leads', ['name' => $name, 'phone' => '+880 1711-000000', 'source_id' => $source, ...$fields])->assertCreated()->json('data.id');
    }

    /** An opportunity added at $at (UTC), at $stage, expected to close on $closeOn. */
    private function opportunity(string $name, string $at, string $stage, string $closeOn, array $fields = []): int
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));

        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => $name, 'contact_id' => $this->contact->id, 'stage_id' => $this->stages[$stage],
            'expected_value' => '1000', 'currency' => 'USD', 'expected_close_date' => $closeOn, ...$fields,
        ])->assertCreated()->json('data.id');
    }

    /** A call for $opportunity, planned at $at (UTC), due at $dueOn 10:00 in Dhaka. */
    private function planCall(int $opportunity, string $at, string $dueOn): int
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));
        $type = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->firstWhere('key', 'call')['id'];

        return $this->postJson('/api/v1/crm/activities', ['opportunity_id' => $opportunity, 'type_id' => $type, 'subject' => 'Call Rahim', 'due_on' => $dueOn, 'due_time' => '10:00'])->assertCreated()->json('data.id');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_leads_in_the_queue_or_not_contacted_in_time_are_counted_and_listed(): void
    {
        // Four days ago: one in the queue, one never contacted, one contacted the next day, one archived; yesterday, one not late yet.
        $this->lead('Queued', '2026-11-14 06:00:00', ['owner_id' => null]);
        $this->lead('Waiting', '2026-11-14 07:00:00');
        $answered = $this->lead('Answered', '2026-11-14 08:00:00');
        $archived = $this->lead('Archived', '2026-11-14 09:00:00');
        $this->lead('Fresh', '2026-11-17 06:00:00');
        $this->travelTo(Carbon::parse('2026-11-15 06:00:00', 'UTC'));
        $this->postJson("/api/v1/crm/leads/{$answered}/contact")->assertOk();
        $this->postJson("/api/v1/crm/leads/{$archived}/archive")->assertOk();
        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));

        $this->overview()
            ->assertJsonPath('data.lead_response_days', 2)
            ->assertJsonPath('data.leads.unassigned', 1)
            ->assertJsonPath('data.leads.uncontacted', 2);

        // Each opens its list: the queue, and those not contacted in time - newest first.
        $this->assertSame(['Queued'], $this->names('leads', ['status' => 'new']));
        $this->assertSame(['Waiting', 'Queued'], $this->names('leads', ['status' => 'uncontacted']));
        $this->assertSame(['Waiting'], $this->names('leads', ['status' => 'uncontacted', 'owner_id' => 'me']));

        // Five days to answer: none late; the Leads page's filter is named with them.
        $this->putJson('/api/v1/business-settings/crm', ['lead_response_days' => 5])->assertOk()->assertJsonPath('data.lead_response_days', 5);
        $this->overview()->assertJsonPath('data.leads.uncontacted', 0);
        $this->assertSame([], $this->names('leads', ['status' => 'uncontacted']));
        $this->getJson('/api/v1/crm/leads/options')->assertJsonPath('data.lead_response_days', 5);

        $this->putJson('/api/v1/business-settings/crm', ['lead_response_days' => 0])->assertUnprocessable()->assertJsonValidationErrors('lead_response_days');
        $this->putJson('/api/v1/business-settings/crm', ['recent_activity_days' => 366])->assertUnprocessable()->assertJsonValidationErrors('recent_activity_days');
        $this->getJson('/api/v1/crm/leads?status=late')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_opportunities_without_a_next_activity_stale_or_quiet_are_counted_and_listed(): void
    {
        CrmStage::query()->whereKey($this->stages['New'])->update(['stale_after_days' => 7]);

        // Twenty days ago.
        $quiet   = $this->opportunity('Quiet', '2026-10-29 06:00:00', 'Proposal', '2026-11-28');
        $emailed = $this->opportunity('E-mailed', '2026-10-29 06:00:00', 'Proposal', '2026-11-25');
        $planned = $this->opportunity('Planned', '2026-10-29 06:00:00', 'Qualified', '2027-01-10');
        $this->opportunity('Stale', '2026-10-29 06:00:00', 'New', '2027-02-01');
        $called  = $this->opportunity('Called long ago', '2026-10-29 06:00:00', 'Proposal', '2026-12-01');
        $won     = $this->opportunity('Won', '2026-10-29 06:00:00', 'Proposal', '2026-11-30');
        $shelved = $this->opportunity('Archived', '2026-10-29 06:00:00', 'Proposal', '2026-11-30');
        // Six days ago: added lately, so not quiet yet.
        $this->opportunity('Added lately', '2026-11-12 06:00:00', 'Proposal', '2026-11-30');

        $this->planCall($planned, '2026-10-29 07:00:00', '2026-11-20');
        $this->postJson('/api/v1/crm/activities/'.$this->planCall($called, '2026-10-30 06:00:00', '2026-10-30').'/complete', ['outcome' => 'Wants a quotation'])->assertOk();
        $this->travelTo(Carbon::parse('2026-11-10 06:00:00', 'UTC'));
        $this->postJson('/api/v1/crm/emails', ['opportunity_id' => $emailed, 'to' => 'rahim@uddin.test', 'subject' => 'Your racking', 'body' => 'Dear Rahim, here it is.'])->assertCreated();
        $this->postJson("/api/v1/crm/opportunities/{$won}/win", ['closed_on' => '2026-11-10'])->assertOk();
        $this->postJson("/api/v1/crm/opportunities/{$shelved}/archive")->assertOk();
        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));

        $this->overview()
            ->assertJsonPath('data.recent_activity_days', 14)
            ->assertJsonPath('data.follow_up', ['without_next_activity' => 5, 'stale' => 1, 'quiet' => 2]);

        // Each opens its list, by expected closing day.
        $this->assertSame(['E-mailed', 'Quiet', 'Added lately', 'Called long ago', 'Stale'], $this->names('opportunities', ['status' => 'no_activity']));
        $this->assertSame(['Stale'], $this->names('opportunities', ['status' => 'stale']));
        $this->assertSame(['Quiet', 'Called long ago'], $this->names('opportunities', ['status' => 'quiet']));
        $this->assertSame(['Quiet'], $this->names('opportunities', ['status' => 'quiet', 'stage_id' => $this->stages['Proposal'], 'search' => 'Quiet']));

        // Planning one takes it off "no next activity"; doing it takes it off "quiet" too.
        $call = $this->planCall($quiet, '2026-11-18 06:00:00', '2026-11-18');
        $this->overview()->assertJsonPath('data.follow_up', ['without_next_activity' => 4, 'stale' => 1, 'quiet' => 2]);
        $this->postJson("/api/v1/crm/activities/{$call}/complete", ['outcome' => 'Signing next week'])->assertOk();
        $this->overview()->assertJsonPath('data.follow_up', ['without_next_activity' => 5, 'stale' => 1, 'quiet' => 1]);

        // Thirty days of recent activity: the one called on 30 October has had some.
        $this->putJson('/api/v1/business-settings/crm', ['recent_activity_days' => 30])->assertOk()->assertJsonPath('data.recent_activity_days', 30);
        $this->overview()->assertJsonPath('data.follow_up.quiet', 0);
        $this->getJson('/api/v1/crm/opportunities/options')->assertJsonPath('data.recent_activity_days', 30)->assertJsonPath('data.closing_soon_days', 30);
        $this->getJson('/api/v1/crm/opportunities?status=late')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_the_warnings_count_what_each_sees_and_the_filters_narrow(): void
    {
        $seller = $this->member(['crm.leads.view', 'crm.leads.manage', 'crm.opportunities.view', 'crm.opportunities.manage', 'contacts.view']);

        $this->lead('Mine', '2026-11-10 06:00:00');
        $this->lead('Theirs', '2026-11-10 06:00:00', ['owner_id' => $seller->id]);
        $this->opportunity('Mine', '2026-11-10 06:00:00', 'Proposal', '2026-12-31');
        $this->opportunity('Theirs', '2026-11-10 06:00:00', 'Proposal', '2026-12-31', ['owner_id' => $seller->id]);
        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));

        $this->overview()->assertJsonPath('data.leads.uncontacted', 2)->assertJsonPath('data.follow_up.without_next_activity', 2);
        $this->overview(['owner_id' => $seller->id])->assertJsonPath('data.leads.uncontacted', 1)->assertJsonPath('data.follow_up.without_next_activity', 1);

        Sanctum::actingAs($seller);
        $this->overview()->assertJsonPath('data.leads.uncontacted', 1)->assertJsonPath('data.follow_up.without_next_activity', 1);
        $this->assertSame(['Theirs'], $this->names('leads', ['status' => 'uncontacted']));
        $this->assertSame(['Theirs'], $this->names('opportunities', ['status' => 'no_activity']));

        // Working with opportunities only: no lead warnings.
        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'opportunities'])->assertOk();
        $this->overview()->assertJsonPath('data.leads', null)->assertJsonPath('data.follow_up.without_next_activity', 2);
    }
}
