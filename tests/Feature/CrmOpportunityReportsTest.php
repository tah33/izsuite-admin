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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase H2: CRM's opportunity reports (section 17.29) - Opportunities
 * by Stage, Pipeline Value, Weighted Forecast, Stage Duration, Opportunities
 * Closing Soon, Won and Lost Opportunities, Win Rate and Lost Reasons - of
 * the opportunities the user sees, archived ones left out, values in the
 * base currency. Phase H3's need the same, or View Activities. Wednesday
 * 18 November 2026, 06:00 UTC - 12:00 in Dhaka.
 */
class CrmOpportunityReportsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const ACTIVITY_KEYS = ['activity-completion', 'overdue-activities'];

    /** Both, in the catalog's order: section 17.29's. */
    private const ALL_KEYS = [
        'opportunities-by-stage', 'pipeline-value', 'weighted-forecast', 'stage-duration', 'opportunities-closing-soon', 'won-lost-opportunities',
        'win-rate', 'lost-reasons', 'sales-team-performance', 'salesperson-performance', 'activity-completion', 'overdue-activities',
        'opportunities-without-follow-up',
    ];

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

    /** Of $keys, those the catalog lists, in its order. */
    private function listed(array $keys): array
    {
        return array_values(array_intersect(array_column($this->getJson('/api/v1/reports')->assertOk()->json('data'), 'key'), $keys));
    }

    /** An opportunity at $stage worth $value USD, expected to close on $closeOn, added at $at (UTC). */
    private function opportunity(string $name, string $stage, string $value, string $closeOn, string $at = '2026-11-18 06:00:00', array $fields = []): int
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));

        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => $name, 'contact_id' => $this->contact->id, 'stage_id' => $this->stages[$stage],
            'expected_value' => $value, 'currency' => 'USD', 'expected_close_date' => $closeOn, ...$fields,
        ])->assertCreated()->json('data.id');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_opportunity_and_activity_reports_are_listed_for_whoever_may_see_them(): void
    {
        $this->assertSame(self::ALL_KEYS, $this->listed(self::ALL_KEYS));
        $this->assertCount(19, array_filter(\App\Services\Reports\ReportCatalog::namesFor('crm'), fn (array $report) => ! $report['shared']));

        // View CRM Reports with View Opportunities; the activity reports want View Activities.
        Sanctum::actingAs($this->member(['reports.view', 'crm.reports.view', 'crm.opportunities.view']));
        $this->assertSame([
            'opportunities-by-stage', 'pipeline-value', 'weighted-forecast', 'stage-duration', 'opportunities-closing-soon', 'won-lost-opportunities',
            'win-rate', 'lost-reasons', 'sales-team-performance', 'salesperson-performance', 'opportunities-without-follow-up',
        ], $this->listed(self::ALL_KEYS));
        $this->getJson('/api/v1/reports/overdue-activities')->assertNotFound();
        Sanctum::actingAs($this->member(['reports.view', 'crm.reports.view', 'crm.activities.view']));
        $this->assertSame(self::ACTIVITY_KEYS, $this->listed(self::ALL_KEYS));
        Sanctum::actingAs($this->member(['reports.view', 'crm.opportunities.view', 'crm.activities.view']));
        $this->assertSame([], $this->listed(self::ALL_KEYS));

        // Working with opportunities only, they stay; with CRM off, they go.
        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'opportunities'])->assertOk();
        $this->assertCount(13, $this->listed(self::ALL_KEYS));
        $this->postJson('/api/v1/apps/crm/deactivate')->assertOk();
        $this->assertSame([], $this->listed(self::ALL_KEYS));
    }

    public function test_the_open_pipeline_by_stage_in_value_and_as_forecast(): void
    {
        CrmStage::query()->whereKey($this->stages['New'])->update(['stale_after_days' => 7]);

        // At New ten days, so stale; two at Proposal from today; one won this month, and an archived one.
        $this->opportunity('Ten days', 'New', '1000', '2026-11-28', '2026-11-08 06:00:00');
        $this->opportunity('Soon', 'Proposal', '2000', '2026-12-15');
        $this->opportunity('Later', 'Proposal', '3000', '2027-03-10');
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Won', 'Qualified', '500', '2026-12-01').'/win', ['closed_on' => '2026-11-10'])->assertOk();
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Shelved', 'Proposal', '9000', '2026-12-01').'/archive')->assertOk();

        $byStage = $this->report('opportunities-by-stage')->json('data');
        $this->assertSame(['New', 'Qualified', 'Proposal', 'Negotiation'], array_column($byStage['rows'], 'group'));
        $this->assertSame(['group' => 'New', 'count' => 1, 'value' => '1000.00', 'weighted' => '100.00', 'days' => '10.0', 'stale' => 1, 'link' => null], $byStage['rows'][0]);
        $this->assertSame(['group' => 'Proposal', 'count' => 2, 'value' => '5000.00', 'weighted' => '2500.00', 'days' => '0.0', 'stale' => 0, 'link' => null], $byStage['rows'][2]);
        $this->assertSame(['group' => 'Total', 'count' => 3, 'value' => '6000.00', 'weighted' => '2600.00', 'days' => '3.3', 'stale' => 1], $byStage['totals']);

        // Pipeline Value is by stage unless told; a stage with nothing open has no row.
        $this->report('pipeline-value')
            ->assertJsonPath('data.rows', [
                ['group' => 'New', 'count' => 1, 'value' => '1000.00', 'share' => '16.7', 'link' => null],
                ['group' => 'Proposal', 'count' => 2, 'value' => '5000.00', 'share' => '83.3', 'link' => null],
            ])
            ->assertJsonPath('data.totals', ['group' => 'Total', 'count' => 3, 'value' => '6000.00', 'share' => '100.0']);
        $this->report('pipeline-value', ['group_by' => 'owner'])->assertJsonPath('data.rows.0.group', $this->user->name)->assertJsonPath('data.columns.0.label', 'Owner');

        // Weighted Forecast is by closing month unless told, as the Forecast page.
        $forecast = $this->report('weighted-forecast')->json('data');
        $this->assertCount(12, $forecast['rows']);
        $this->assertSame(['group' => 'November 2026', 'count' => 1, 'value' => '1000.00', 'weighted' => '100.00', 'won' => '500.00', 'link' => null], $forecast['rows'][0]);
        $this->assertSame(['group' => 'March 2027', 'count' => 1, 'value' => '3000.00', 'weighted' => '1500.00', 'won' => '0.00', 'link' => null], $forecast['rows'][4]);
        $this->assertSame(['group' => 'Total', 'count' => 3, 'value' => '6000.00', 'weighted' => '2600.00', 'won' => '500.00'], $forecast['totals']);
        $this->report('weighted-forecast', ['stage_id' => $this->stages['Proposal']])->assertJsonPath('data.totals.count', 2);
        $this->report('opportunities-by-stage', ['owner_id' => $this->member(['crm.opportunities.view'])->id])->assertJsonPath('data.totals.count', 0);
    }

    public function test_won_and_lost_the_win_rate_and_the_lost_reasons_for_the_period(): void
    {
        $reasons = collect($this->getJson('/api/v1/crm/opportunities/options')->json('data.lost_reasons'));
        [$first, $second] = [$reasons[0], $reasons[1]];
        $close   = fn (string $step, int $id, array $body) => $this->postJson("/api/v1/crm/opportunities/{$id}/{$step}", $body)->assertOk();

        $close('win', $this->opportunity('W1', 'Proposal', '500', '2026-12-01'), ['closed_on' => '2026-11-05']);
        $close('win', $w2 = $this->opportunity('W2', 'Proposal', '1500', '2026-12-01'), ['closed_on' => '2026-11-12']);
        $close('lose', $this->opportunity('L1', 'Proposal', '300', '2026-12-01'), ['closed_on' => '2026-11-08', 'lost_reason_id' => $first['id'], 'competitor' => 'Rival Co']);
        $close('lose', $this->opportunity('L2', 'Qualified', '700', '2026-12-01'), ['closed_on' => '2026-11-09', 'lost_reason_id' => $first['id'], 'competitor' => 'Rival Co']);
        $close('lose', $this->opportunity('L3', 'New', '200', '2026-12-01'), ['closed_on' => '2026-11-10', 'lost_reason_id' => $second['id']]);
        $close('win', $this->opportunity('October', 'Proposal', '900', '2026-12-01'), ['closed_on' => '2026-10-20']);

        $closed = $this->report('won-lost-opportunities')->json('data');
        $this->assertSame(['W2', 'L3', 'L2', 'L1', 'W1'], array_column($closed['rows'], 'opportunity'));
        $this->assertSame(['2026-11-12', 'Won', '1500.00', 'USD', "/crm/opportunities/{$w2}"], [$closed['rows'][0]['closed_on'], $closed['rows'][0]['outcome'], $closed['rows'][0]['value'], $closed['rows'][0]['currency'], $closed['rows'][0]['link']]);
        $this->assertSame([$first['name'], 'Rival Co'], [$closed['rows'][2]['reason'], $closed['rows'][2]['competitor']]);
        $this->assertSame([['label' => 'Won (2)', 'value' => '2000.00', 'currency' => 'USD'], ['label' => 'Lost (3)', 'value' => '1200.00', 'currency' => 'USD']], $closed['summary']);

        $this->report('win-rate')
            ->assertJsonPath('data.rows', [['group' => 'Nov 2026', 'closed' => 5, 'won' => 2, 'lost' => 3, 'rate' => '40.0', 'won_value' => '2000.00', 'lost_value' => '1200.00', 'value_rate' => '62.5', 'link' => null]]);
        $year = $this->report('win-rate', ['range' => 'this_year'])->json('data');
        $this->assertCount(12, $year['rows']);
        $this->assertSame(['Oct 2026', 1, 1, '100.0'], [$year['rows'][9]['group'], $year['rows'][9]['closed'], $year['rows'][9]['won'], $year['rows'][9]['rate']]);
        $this->assertSame(['closed' => 6, 'won' => 3, 'rate' => '50.0'], array_intersect_key($year['totals'], array_flip(['closed', 'won', 'rate'])));
        $this->report('win-rate', ['group_by' => 'owner'])->assertJsonPath('data.rows.0.group', $this->user->name)->assertJsonCount(1, 'data.rows');

        $this->report('lost-reasons')
            ->assertJsonPath('data.rows', [
                ['group' => $first['name'], 'count' => 2, 'share' => '66.7', 'value' => '1000.00', 'competitor' => 'Rival Co', 'link' => null],
                ['group' => $second['name'], 'count' => 1, 'share' => '33.3', 'value' => '200.00', 'competitor' => null, 'link' => null],
            ])
            ->assertJsonPath('data.totals', ['group' => 'Total', 'count' => 3, 'share' => '100.0', 'value' => '1200.00']);
    }

    public function test_stage_duration_and_the_opportunities_closing_soon(): void
    {
        // Four days at New, six at Qualified, at Proposal since; another at New for eight days.
        $moved = $this->opportunity('Moved', 'New', '1000', '2027-06-30', '2026-11-01 06:00:00');
        $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));
        $this->postJson("/api/v1/crm/opportunities/{$moved}/stage", ['stage_id' => $this->stages['Qualified']])->assertOk();
        $this->travelTo(Carbon::parse('2026-11-11 06:00:00', 'UTC'));
        $this->postJson("/api/v1/crm/opportunities/{$moved}/stage", ['stage_id' => $this->stages['Proposal']])->assertOk();
        $this->opportunity('Waiting', 'New', '1000', '2027-06-30', '2026-11-10 06:00:00');
        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));

        $duration = $this->report('stage-duration')->json('data');
        $this->assertSame(['group' => 'New', 'moved' => 1, 'average' => '4.0', 'longest' => 4, 'there' => 1, 'so_far' => '8.0', 'link' => null], $duration['rows'][0]);
        $this->assertSame(['group' => 'Qualified', 'moved' => 1, 'average' => '6.0', 'longest' => 6, 'there' => 0, 'so_far' => null, 'link' => null], $duration['rows'][1]);
        $this->assertSame(['group' => 'Proposal', 'moved' => 0, 'average' => null, 'longest' => null, 'there' => 1, 'so_far' => '7.0', 'link' => null], $duration['rows'][2]);
        $this->assertSame(['group' => 'Total', 'moved' => 2, 'average' => '5.0', 'longest' => 6, 'there' => 2, 'so_far' => '7.5'], $duration['totals']);
        $this->report('stage-duration', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'])->assertJsonPath('data.totals.moved', 0);

        // Within the 30 days of the closing-soon period, the soonest first.
        $first = $this->opportunity('Two days', 'Proposal', '1000', '2026-11-20');
        $this->opportunity('Three weeks', 'New', '2000', '2026-12-10');
        $this->opportunity('January', 'Proposal', '4000', '2027-01-30');

        $soon = $this->report('opportunities-closing-soon')->json('data');
        $this->assertSame(['Two days', 'Three weeks'], array_column($soon['rows'], 'opportunity'));
        $this->assertSame([2, 22], array_column($soon['rows'], 'days_left'));
        $this->assertSame(['Proposal', '50', '500.00', "/crm/opportunities/{$first}"], [$soon['rows'][0]['stage'], $soon['rows'][0]['probability'], $soon['rows'][0]['weighted'], $soon['rows'][0]['link']]);
        $this->assertSame([['label' => '2 opportunities, expected', 'value' => '3000.00', 'currency' => 'USD'], ['label' => 'Weighted', 'value' => '700.00', 'currency' => 'USD']], $soon['summary']);
        $this->getJson('/api/v1/reports/win-rate?group_by=carrier')->assertOk()->assertJsonPath('data.columns.0.label', 'Month');
    }
}
