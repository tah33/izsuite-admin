<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Reports\ReportCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase H1: CRM's lead reports on the Reports page (section 17.29) -
 * Leads by Source, by Status and by Owner, Lead Response Time, Lead
 * Qualification Rate and Lead Conversion Rate - of the leads added in the
 * period that the user sees, for whoever may view CRM reports and leads,
 * while CRM is on and the business uses leads. Wednesday 18 November 2026,
 * 06:00 UTC - 12:00 in Dhaka.
 */
class CrmLeadReportsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const KEYS = ['leads-by-source', 'leads-by-status', 'leads-by-owner', 'lead-response-time', 'lead-qualification-rate', 'lead-conversion-rate'];

    private User $user;

    private Workspace $business;

    /** @var array<string, int> the lead sources by name */
    private array $sources;

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

        $this->sources = collect($this->getJson('/api/v1/crm/leads/options')->json('data.sources'))->pluck('id', 'name')->all();
    }

    private function report(string $key, array $query = []): TestResponse
    {
        return $this->getJson("/api/v1/reports/{$key}?".http_build_query($query))->assertOk();
    }

    /** The CRM reports the catalog lists. */
    private function listed(): array
    {
        return array_values(array_intersect(array_column($this->getJson('/api/v1/reports')->assertOk()->json('data'), 'key'), self::KEYS));
    }

    /** A lead from $source added at $addedAt (UTC) with $status, first contacted at $contactedAt. */
    private function lead(string $name, string $source, string $status, string $addedAt, ?string $contactedAt = null, array $fields = []): int
    {
        $id = $this->postJson('/api/v1/crm/leads', ['name' => $name, 'phone' => '+880 1711-000000', 'source_id' => $this->sources[$source], ...$fields])->assertCreated()->json('data.id');

        CrmLead::query()->whereKey($id)->update(['status' => $status, 'created_at' => $addedAt, 'first_contacted_at' => $contactedAt]);

        return $id;
    }

    private function member(array $permissions, array $fields = []): User
    {
        $user = User::factory()->create($fields);
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_crm_reports_are_listed_while_crm_is_on_for_whoever_may_see_them(): void
    {
        $this->assertSame(self::KEYS, $this->listed());
        $this->assertSame('CRM', collect($this->getJson('/api/v1/reports')->json('data'))->firstWhere('key', 'leads-by-source')['category']);
        $this->assertSame(['Leads by Source', 'Leads by Status', 'Leads by Owner', 'Lead Response Time', 'Lead Qualification Rate', 'Lead Conversion Rate'], array_column(ReportCatalog::namesFor('crm'), 'name'));

        // View CRM Reports with View Leads; either alone is not enough.
        Sanctum::actingAs($this->member(['reports.view', 'crm.leads.view']));
        $this->assertSame([], $this->listed());
        $this->getJson('/api/v1/reports/leads-by-source')->assertNotFound();
        Sanctum::actingAs($this->member(['reports.view', 'crm.reports.view', 'crm.opportunities.view']));
        $this->assertSame([], $this->listed());
        Sanctum::actingAs($this->member(['reports.view', 'crm.reports.view', 'crm.leads.view']));
        $this->assertSame(self::KEYS, $this->listed());

        // Working with opportunities only, and with CRM off: none.
        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'opportunities'])->assertOk();
        $this->assertSame([], $this->listed());
        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'leads'])->assertOk();
        $this->postJson('/api/v1/apps/crm/deactivate')->assertOk();
        $this->assertSame([], $this->listed());
    }

    public function test_leads_by_source_owner_and_status_count_the_period_s_leads(): void
    {
        $seller = $this->member(['reports.view', 'crm.reports.view', 'crm.leads.view', 'crm.leads.manage'], ['first_name' => 'Rahim', 'last_name' => 'Uddin']);

        $this->lead('Old', 'Website', CrmLead::CONVERTED, '2026-10-15 06:00:00');
        $this->lead('Web new', 'Website', CrmLead::NEW, '2026-11-02 06:00:00', null, ['owner_id' => null]);
        $this->lead('Web contacted', 'Website', CrmLead::CONTACTED, '2026-11-03 06:00:00');
        $moved = $this->lead('Web converted', 'Website', CrmLead::CONVERTED, '2026-11-04 06:00:00');
        $this->lead('Referral qualified', 'Referral', CrmLead::QUALIFIED, '2026-11-05 06:00:00');
        $this->lead('Referral unqualified', 'Referral', CrmLead::UNQUALIFIED, '2026-11-06 06:00:00');
        $this->lead("Rahim's", 'Website', CrmLead::ASSIGNED, '2026-11-07 06:00:00', null, ['owner_id' => $seller->id]);

        // A branch opened since, with one of them.
        $branch = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG'])->id;
        CrmLead::query()->whereKey($moved)->update(['location_id' => $branch]);

        $bySource = $this->report('leads-by-source')
            ->assertJsonPath('data.title', 'Leads by Source')
            ->assertJsonPath('data.rows.0', ['group' => 'Website', 'leads' => 4, 'working' => 3, 'ready' => 0, 'converted' => 1, 'closed' => 0, 'rate' => '25.0', 'link' => null])
            ->assertJsonPath('data.rows.1', ['group' => 'Referral', 'leads' => 2, 'working' => 0, 'ready' => 1, 'converted' => 0, 'closed' => 1, 'rate' => '0.0', 'link' => null])
            ->assertJsonPath('data.totals', ['group' => 'Total', 'leads' => 6, 'working' => 3, 'ready' => 1, 'converted' => 1, 'closed' => 1, 'rate' => '16.7'])
            ->json('data');
        $this->assertCount(2, $bySource['rows']);

        $this->report('leads-by-owner')
            ->assertJsonPath('data.rows.0.leads', 4)
            ->assertJsonPath('data.rows.1', ['group' => 'No owner', 'leads' => 1, 'working' => 1, 'ready' => 0, 'converted' => 0, 'closed' => 0, 'rate' => '0.0', 'link' => null])
            ->assertJsonPath('data.rows.2.group', 'Rahim Uddin');

        $byStatus = $this->report('leads-by-status')->json('data');
        $this->assertSame(['New', 'Assigned', 'Contacted', 'Qualified', 'Unqualified', 'Converted', 'Archived'], array_column($byStatus['rows'], 'group'));
        $this->assertSame([1, 1, 1, 1, 1, 1, 0], array_column($byStatus['rows'], 'leads'));
        $this->assertSame(['16.7', '0.0'], [$byStatus['rows'][0]['share'], $byStatus['rows'][6]['share']]);
        $this->assertSame(['group' => 'Total', 'leads' => 6, 'share' => '100.0'], $byStatus['totals']);

        // The filters narrow them, a range brings in other months, and an owner sees their own.
        $this->report('leads-by-source', ['owner_id' => $seller->id])->assertJsonPath('data.totals.leads', 1);
        $this->report('leads-by-status', ['lead_source_id' => $this->sources['Referral']])->assertJsonPath('data.totals.leads', 2);
        $this->report('leads-by-status', ['location_id' => $branch])->assertJsonPath('data.totals.leads', 1);
        $this->report('leads-by-source', ['date_from' => '2026-10-01', 'date_to' => '2026-11-30'])->assertJsonPath('data.totals.leads', 7)->assertJsonPath('data.rows.0.converted', 2);
        Sanctum::actingAs($seller);
        $this->report('leads-by-source')->assertJsonPath('data.totals', ['group' => 'Total', 'leads' => 1, 'working' => 1, 'ready' => 0, 'converted' => 0, 'closed' => 0, 'rate' => '0.0']);
    }

    public function test_lead_response_time_counts_first_contacts_against_the_lead_response_period(): void
    {
        // Contacted after six hours, after three days, and not yet.
        $this->lead('Quick', 'Website', CrmLead::CONTACTED, '2026-11-02 06:00:00', '2026-11-02 12:00:00');
        $this->lead('Slow', 'Referral', CrmLead::QUALIFIED, '2026-11-03 06:00:00', '2026-11-06 06:00:00');
        $this->lead('Waiting', 'Website', CrmLead::ASSIGNED, '2026-11-04 06:00:00');

        $this->report('lead-response-time')
            ->assertJsonPath('data.columns.3.label', 'Within 2 Days')
            ->assertJsonPath('data.rows.0', ['group' => 'Nov 2026', 'leads' => 3, 'contacted' => 2, 'in_time' => 1, 'waiting' => 1, 'average' => '39.0', 'in_time_rate' => '33.3', 'link' => null])
            ->assertJsonPath('data.totals.average', '39.0');

        $this->report('lead-response-time', ['group_by' => 'source'])
            ->assertJsonPath('data.columns.0.label', 'Lead Source')
            ->assertJsonPath('data.rows.0', ['group' => 'Website', 'leads' => 2, 'contacted' => 1, 'in_time' => 1, 'waiting' => 1, 'average' => '6.0', 'in_time_rate' => '50.0', 'link' => null])
            ->assertJsonPath('data.rows.1.average', '72.0');

        // Five days to answer: both in time.
        $this->putJson('/api/v1/business-settings/crm', ['lead_response_days' => 5])->assertOk();
        $this->report('lead-response-time')->assertJsonPath('data.columns.3.label', 'Within 5 Days')->assertJsonPath('data.totals.in_time', 2);
        $this->getJson('/api/v1/reports/lead-response-time?group_by=status')->assertUnprocessable()->assertJsonValidationErrors('group_by');
    }

    public function test_qualification_and_conversion_rates_by_month(): void
    {
        $this->lead('October converted', 'Website', CrmLead::CONVERTED, '2026-10-10 06:00:00');
        $this->lead('October unqualified', 'Website', CrmLead::UNQUALIFIED, '2026-10-11 06:00:00');
        $this->lead('November qualified', 'Referral', CrmLead::QUALIFIED, '2026-11-02 06:00:00');
        $this->lead('November converted', 'Referral', CrmLead::CONVERTED, '2026-11-03 06:00:00');
        $this->lead('November new', 'Website', CrmLead::NEW, '2026-11-04 06:00:00', null, ['owner_id' => null]);

        // A row for every month of the year, quiet ones too.
        $qualification = $this->report('lead-qualification-rate', ['range' => 'this_year'])->assertJsonPath('data.title', 'Lead Qualification Rate')->json('data');
        $this->assertCount(12, $qualification['rows']);
        $this->assertSame(['group' => 'Oct 2026', 'leads' => 2, 'qualified' => 1, 'unqualified' => 1, 'working' => 0, 'archived' => 0, 'qualification_rate' => '50.0', 'link' => null], $qualification['rows'][9]);
        $this->assertSame(['group' => 'Nov 2026', 'leads' => 3, 'qualified' => 2, 'unqualified' => 0, 'working' => 1, 'archived' => 0, 'qualification_rate' => '66.7', 'link' => null], $qualification['rows'][10]);
        $this->assertNull($qualification['rows'][0]['qualification_rate']);
        $this->assertSame('60.0', $qualification['totals']['qualification_rate']);

        $this->report('lead-conversion-rate', ['range' => 'this_year'])
            ->assertJsonPath('data.rows.9', ['group' => 'Oct 2026', 'leads' => 2, 'qualified' => 1, 'converted' => 1, 'conversion_rate' => '50.0', 'of_qualified' => '100.0', 'link' => null])
            ->assertJsonPath('data.rows.10', ['group' => 'Nov 2026', 'leads' => 3, 'qualified' => 2, 'converted' => 1, 'conversion_rate' => '33.3', 'of_qualified' => '50.0', 'link' => null])
            ->assertJsonPath('data.totals', ['group' => 'Total', 'leads' => 5, 'qualified' => 3, 'converted' => 2, 'conversion_rate' => '40.0', 'of_qualified' => '66.7']);

        $this->report('lead-conversion-rate', ['range' => 'this_year', 'group_by' => 'source'])
            ->assertJsonPath('data.rows.0.group', 'Website')
            ->assertJsonPath('data.rows.0.conversion_rate', '33.3');
    }
}
