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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase H3: CRM's people and activity reports (section 17.29) -
 * Sales Team Performance, Salesperson Performance, Activity Completion,
 * Overdue Activities and Opportunities Without Follow-up - each counting only
 * what the user sees. Wednesday 18 November 2026, 06:00 UTC - 12:00 in Dhaka.
 */
class CrmPeopleReportsTest extends TestCase
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

    private function report(string $key, array $query = []): TestResponse
    {
        return $this->getJson("/api/v1/reports/{$key}?".http_build_query($query))->assertOk();
    }

    /** An opportunity at $stage worth $value USD, added at $at (UTC). */
    private function opportunity(string $name, string $stage, string $value, string $at = '2026-11-18 06:00:00', array $fields = []): int
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));

        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => $name, 'contact_id' => $this->contact->id, 'stage_id' => $this->stages[$stage],
            'expected_value' => $value, 'currency' => 'USD', 'expected_close_date' => '2027-01-31', ...$fields,
        ])->assertCreated()->json('data.id');
    }

    /** A call for $opportunity planned at $at (UTC), due on $dueOn at 10:00 in Dhaka. */
    private function planCall(int $opportunity, string $subject, string $dueOn, string $at = '2026-11-01 06:00:00', array $fields = []): int
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));
        $type = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->firstWhere('key', 'call')['id'];

        return $this->postJson('/api/v1/crm/activities', ['opportunity_id' => $opportunity, 'type_id' => $type, 'subject' => $subject, 'due_on' => $dueOn, 'due_time' => '10:00', ...$fields])->assertCreated()->json('data.id');
    }

    /** $activity completed at $at (UTC). */
    private function complete(int $activity, string $at): void
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));
        $this->postJson("/api/v1/crm/activities/{$activity}/complete", ['outcome' => 'Spoke to Rahim'])->assertOk();
    }

    private function member(array $permissions, array $fields = []): User
    {
        $user = User::factory()->create($fields);
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_salesperson_and_sales_team_performance(): void
    {
        $seller = $this->member(['crm.leads.view', 'crm.leads.manage', 'crm.opportunities.view', 'crm.opportunities.manage', 'crm.activities.view', 'crm.activities.manage', 'contacts.view'], ['first_name' => 'Karim', 'last_name' => 'Ali']);
        $team   = $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'Wholesale Desk', 'is_active' => true, 'member_ids' => [$seller->id]])->assertCreated()->json('data.id');
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');
        $lead   = fn (string $name, array $fields = []) => $this->postJson('/api/v1/crm/leads', ['name' => $name, 'phone' => '+880 1711-000000', 'source_id' => $source, ...$fields])->assertCreated()->json('data.id');

        CrmLead::query()->whereKey($lead('Mine, converted'))->update(['status' => CrmLead::CONVERTED]);
        $lead('Mine');
        $lead("Karim's", ['owner_id' => $seller->id, 'sales_team_id' => $team]);

        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Mine won', 'Proposal', '1000').'/win', ['closed_on' => '2026-11-10'])->assertOk();
        $reason = $this->getJson('/api/v1/crm/opportunities/options')->json('data.lost_reasons.0.id');
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Mine lost', 'Proposal', '400').'/lose', ['closed_on' => '2026-11-12', 'lost_reason_id' => $reason])->assertOk();
        $mine = $this->opportunity('Mine open', 'Proposal', '2000');
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity("Karim's won", 'Qualified', '500', fields: ['owner_id' => $seller->id, 'sales_team_id' => $team]).'/win', ['closed_on' => '2026-11-11'])->assertOk();
        $karims = $this->opportunity("Karim's open", 'New', '3000', fields: ['owner_id' => $seller->id, 'sales_team_id' => $team]);

        $this->complete($this->planCall($mine, 'First call', '2026-11-03'), '2026-11-03 05:00:00');
        $this->complete($this->planCall($mine, 'Second call', '2026-11-06'), '2026-11-06 05:00:00');
        $this->complete($this->planCall($karims, "Karim's call", '2026-11-09', fields: ['assigned_to' => $seller->id]), '2026-11-09 05:00:00');
        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));

        $people = $this->report('salesperson-performance')->json('data');
        $this->assertSame(['group', 'leads', 'converted', 'won', 'lost', 'rate', 'won_value', 'pipeline', 'done'], array_column($people['columns'], 'key'));
        $this->assertSame(['group' => $this->user->name, 'leads' => 2, 'converted' => 1, 'won' => 1, 'lost' => 1, 'rate' => '50.0', 'won_value' => '1000.00', 'pipeline' => '2000.00', 'done' => 2, 'link' => null], $people['rows'][0]);
        $this->assertSame(['group' => 'Karim Ali', 'leads' => 1, 'converted' => 0, 'won' => 1, 'lost' => 0, 'rate' => '100.0', 'won_value' => '500.00', 'pipeline' => '3000.00', 'done' => 1, 'link' => null], $people['rows'][1]);
        $this->assertSame(['group' => 'Total', 'leads' => 3, 'converted' => 1, 'won' => 2, 'lost' => 1, 'rate' => '66.7', 'won_value' => '1500.00', 'pipeline' => '5000.00', 'done' => 3], $people['totals']);
        $this->report('salesperson-performance', ['sales_team_id' => $team])->assertJsonPath('data.totals', ['group' => 'Total', 'leads' => 1, 'converted' => 0, 'won' => 1, 'lost' => 0, 'rate' => '100.0', 'won_value' => '500.00', 'pipeline' => '3000.00', 'done' => 1]);

        // By team, an activity counting for its record's: the most won first.
        $teams = $this->report('sales-team-performance')->json('data');
        $this->assertSame(['No sales team', 'Wholesale Desk'], array_column($teams['rows'], 'group'));
        $this->assertSame([1, 1, '500.00', '3000.00', 1], [$teams['rows'][1]['leads'], $teams['rows'][1]['won'], $teams['rows'][1]['won_value'], $teams['rows'][1]['pipeline'], $teams['rows'][1]['done']]);

        // Without View Leads and View Activities, their columns go; without seeing others', only their own.
        Sanctum::actingAs($this->member(['reports.view', 'crm.reports.view', 'crm.opportunities.view', 'crm.records.all']));
        $this->assertSame(['group', 'won', 'lost', 'rate', 'won_value', 'pipeline'], array_column($this->report('salesperson-performance')->json('data.columns'), 'key'));
        Sanctum::actingAs($this->member(['reports.view', 'crm.reports.view', 'crm.opportunities.view']));
        $this->report('salesperson-performance')->assertJsonPath('data.rows', [])->assertJsonPath('data.totals.won', 0);
    }

    public function test_activity_completion_and_overdue_activities(): void
    {
        $target = $this->opportunity('Target', 'Proposal', '1000', '2026-10-01 06:00:00');

        // Completed before its due time, completed late, cancelled, overdue, still to come; and one overdue since October.
        $this->complete($this->planCall($target, 'On time', '2026-11-10'), '2026-11-10 03:00:00');
        $this->complete($this->planCall($target, 'Late', '2026-11-12'), '2026-11-14 06:00:00');
        $cancelled = $this->planCall($target, 'Called off', '2026-11-15');
        $this->postJson("/api/v1/crm/activities/{$cancelled}/cancel", ['reason' => 'Rahim is away'])->assertOk();
        $this->planCall($target, 'Yesterday', '2026-11-17');
        $this->planCall($target, 'Next week', '2026-11-25');
        $this->planCall($target, 'October', '2026-10-20', '2026-10-01 06:00:00');
        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));

        $this->report('activity-completion')
            ->assertJsonPath('data.rows', [['group' => 'Nov 2026', 'due' => 5, 'completed' => 2, 'on_time' => 1, 'cancelled' => 1, 'planned' => 2, 'overdue' => 1, 'rate' => '50.0', 'on_time_rate' => '50.0', 'link' => null]]);
        $year = $this->report('activity-completion', ['range' => 'this_year'])->json('data');
        $this->assertCount(12, $year['rows']);
        $this->assertSame(['due' => 1, 'completed' => 0, 'planned' => 1, 'overdue' => 1, 'rate' => '0.0'], array_intersect_key($year['rows'][9], array_flip(['due', 'completed', 'planned', 'overdue', 'rate'])));
        $this->report('activity-completion', ['group_by' => 'type'])->assertJsonPath('data.rows.0.group', 'Call')->assertJsonPath('data.columns.0.label', 'Activity Type');
        $this->report('activity-completion', ['group_by' => 'assignee'])->assertJsonPath('data.rows.0.group', $this->user->name);

        $overdue = $this->report('overdue-activities')->json('data');
        $this->assertSame(['October', 'Yesterday'], array_column($overdue['rows'], 'activity'));
        $this->assertSame(['due' => '2026-10-20', 'activity' => 'October', 'type' => 'Call', 'regarding' => 'Opportunity: Target', 'assignee' => $this->user->name, 'days' => 29, 'link' => null], $overdue['rows'][0]);
        $this->assertSame(1, $overdue['rows'][1]['days']);
        $this->report('overdue-activities', ['owner_id' => $this->member(['crm.activities.view'])->id])->assertJsonPath('data.rows', []);
    }

    public function test_opportunities_without_follow_up(): void
    {
        $this->opportunity('Untouched', 'New', '1000', '2026-11-08 06:00:00');
        $planned = $this->opportunity('Planned', 'New', '1000', '2026-11-08 06:00:00');
        $this->planCall($planned, 'Next week', '2026-11-25', '2026-11-08 07:00:00');
        $called  = $this->opportunity('Called', 'Proposal', '2000', '2026-11-08 06:00:00');
        $this->complete($this->planCall($called, 'Called Rahim', '2026-11-15', '2026-11-08 07:00:00'), '2026-11-15 06:00:00');
        $emailed = $this->opportunity('E-mailed', 'Qualified', '500', '2026-11-08 06:00:00');
        $this->travelTo(Carbon::parse('2026-11-16 06:00:00', 'UTC'));
        $this->postJson('/api/v1/crm/emails', ['opportunity_id' => $emailed, 'to' => 'rahim@uddin.test', 'subject' => 'Your racking', 'body' => 'Dear Rahim, here it is.'])->assertCreated();
        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));

        $quiet = $this->report('opportunities-without-follow-up')->json('data');
        $this->assertSame(['Untouched', 'Called', 'E-mailed'], array_column($quiet['rows'], 'opportunity'));
        $this->assertSame([10, 3, 2], array_column($quiet['rows'], 'days'));
        $this->assertSame([null, '2026-11-15', '2026-11-16'], array_column($quiet['rows'], 'last_contact'));
        $this->assertSame([['label' => '3 opportunities, expected', 'value' => '3500.00', 'currency' => 'USD'], ['label' => 'Weighted', 'value' => '1225.00', 'currency' => 'USD']], $quiet['summary']);
        $this->report('opportunities-without-follow-up', ['stage_id' => $this->stages['Proposal']])->assertJsonCount(1, 'data.rows');
    }
}
