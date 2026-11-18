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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase G4: CRM's figures on the Dashboard (section 17.28) - new
 * leads in the Dashboard's range, leads awaiting follow-up, open
 * opportunities, activities due today and overdue, opportunities closing
 * soon, the pipeline value and the weighted forecast - counted as the CRM
 * Overview counts them, only what each user sees, each listed by the page it
 * opens. Wednesday 18 November 2026, 06:00 UTC - 12:00 in Dhaka.
 */
class CrmDashboardTest extends TestCase
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

    private function dashboard(array $query = []): TestResponse
    {
        return $this->getJson('/api/v1/dashboard?'.http_build_query($query))->assertOk();
    }

    /** The names a list shows: $kind is leads or opportunities. */
    private function names(string $kind, array $query): array
    {
        return array_column($this->getJson("/api/v1/crm/{$kind}?".http_build_query($query))->assertOk()->json('data'), 'name');
    }

    /** How many the Activities page lists under $status, anyone's the user sees. */
    private function listedActivities(string $status): int
    {
        return $this->getJson('/api/v1/crm/activities?'.http_build_query(['status' => $status, 'assigned_to' => '']))->assertOk()->json('pagination.total');
    }

    /** A lead added at $at (UTC). */
    private function lead(string $name, string $at, array $fields = []): int
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');

        return $this->postJson('/api/v1/crm/leads', ['name' => $name, 'phone' => '+880 1711-000000', 'source_id' => $source, ...$fields])->assertCreated()->json('data.id');
    }

    /** An open opportunity at Proposal, worth $value USD at $probability %, expected to close on $closeOn. */
    private function opportunity(string $name, string $value, int $probability, string $closeOn, array $fields = []): int
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => $name, 'contact_id' => $this->contact->id, 'stage_id' => $this->stages['Proposal'], 'probability' => $probability,
            'expected_value' => $value, 'currency' => 'USD', 'expected_close_date' => $closeOn, ...$fields,
        ])->assertCreated()->json('data.id');
    }

    /** A call for $opportunity, due on $dueOn at $dueTime in Dhaka. */
    private function planCall(int $opportunity, string $dueOn, string $dueTime, array $fields = []): int
    {
        $type = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->firstWhere('key', 'call')['id'];

        return $this->postJson('/api/v1/crm/activities', ['opportunity_id' => $opportunity, 'type_id' => $type, 'subject' => 'Call Rahim', 'due_on' => $dueOn, 'due_time' => $dueTime, ...$fields])->assertCreated()->json('data.id');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => ['dashboard.view', ...$permissions]]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_the_dashboard_counts_crm_as_the_overview_does_each_figure_opening_its_list(): void
    {
        // This month: one in the queue, one assigned, one contacted since; last month, one still assigned.
        $this->lead('Old', '2026-10-20 06:00:00');
        $this->lead('Queued', '2026-11-03 06:00:00', ['owner_id' => null]);
        $this->lead('Assigned', '2026-11-10 06:00:00');
        $contacted = $this->lead('Contacted', '2026-11-12 06:00:00');
        $this->postJson("/api/v1/crm/leads/{$contacted}/contact")->assertOk();
        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));

        // Open: one closing in ten days, one in February; won and archived ones do not count.
        $soon  = $this->opportunity('Soon', '1000', 40, '2026-11-28');
        $later = $this->opportunity('Later', '2500', 20, '2027-02-01');
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Won', '700', 50, '2026-11-30').'/win', ['closed_on' => '2026-11-15'])->assertOk();
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Archived', '900', 50, '2026-11-25').'/archive')->assertOk();

        // Today: one this morning (so overdue too) and one this afternoon; one overdue since yesterday; one tomorrow.
        $this->planCall($soon, '2026-11-18', '09:00');
        $this->planCall($soon, '2026-11-18', '15:00');
        $this->planCall($later, '2026-11-17', '10:00');
        $this->planCall($later, '2026-11-19', '10:00');

        $crm = $this->dashboard()->assertJsonPath('data.crm', [
            'closing_soon_days' => 30,
            'leads'             => ['new' => 3, 'awaiting' => 3],
            'open'              => ['count' => 2, 'value' => ['amount' => '3500.00', 'unrated' => 0], 'weighted' => ['amount' => '900.00', 'unrated' => 0]],
            'closing_soon'      => 1,
            'activities'        => ['due_today' => 2, 'overdue' => 2],
        ])->json('data.crm');

        // As the Overview counts them.
        $overview = $this->getJson('/api/v1/crm/overview')->assertOk()->json('data');
        $this->assertSame([$overview['leads']['new'], $overview['leads']['awaiting']], [$crm['leads']['new'], $crm['leads']['awaiting']]);
        $this->assertSame([$overview['open'], $overview['closing_soon']['count'], $overview['overdue_activities']], [$crm['open'], $crm['closing_soon'], $crm['activities']['overdue']]);

        // Each opens its list: leads newest first, opportunities by expected closing day.
        $this->assertSame(['Contacted', 'Assigned', 'Queued'], array_slice($this->names('leads', ['status' => 'all']), 0, 3));
        $this->assertSame(['Assigned', 'Queued', 'Old'], $this->names('leads', ['status' => 'awaiting']));
        $this->assertSame(['Soon', 'Later'], $this->names('opportunities', []));
        $this->assertSame(['Soon'], $this->names('opportunities', ['status' => 'closing_soon']));
        $this->assertSame([2, 2], [$this->listedActivities('today'), $this->listedActivities('overdue')]);

        // A longer closing-soon period takes in February; the list's filter is named with it.
        $this->putJson('/api/v1/business-settings/crm', ['closing_soon_days' => 120])->assertOk();
        $this->dashboard()->assertJsonPath('data.crm.closing_soon', 2)->assertJsonPath('data.crm.closing_soon_days', 120);
        $this->assertSame(['Soon', 'Later'], $this->names('opportunities', ['status' => 'closing_soon']));
        $this->getJson('/api/v1/crm/opportunities/options')->assertJsonPath('data.closing_soon_days', 120);
        $this->getJson('/api/v1/crm/leads?status=waiting')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_the_crm_figures_follow_the_range_the_location_and_what_each_may_see(): void
    {
        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK'])->id;
        $ctg   = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG'])->id;
        $seller = $this->member(['crm.opportunities.view', 'crm.opportunities.manage', 'contacts.view']);

        $this->lead('Last month', '2026-10-20 06:00:00', ['location_id' => $dhaka]);
        $this->lead('This month', '2026-11-10 06:00:00', ['location_id' => $ctg]);
        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));
        $mine = $this->opportunity('Mine', '1000', 50, '2026-11-28', ['location_id' => $dhaka]);
        $this->opportunity('Theirs', '3000', 50, '2026-12-31', ['location_id' => $ctg, 'owner_id' => $seller->id]);
        $this->planCall($mine, '2026-11-17', '10:00');

        // The range counts the new leads only; a location narrows them all.
        $this->dashboard(['date_from' => '2026-10-01', 'date_to' => '2026-10-31'])->assertJsonPath('data.crm.leads', ['new' => 1, 'awaiting' => 2])->assertJsonPath('data.crm.open.count', 2);
        $this->dashboard(['location_id' => $dhaka])
            ->assertJsonPath('data.crm.leads', ['new' => 0, 'awaiting' => 1])
            ->assertJsonPath('data.crm.open.count', 1)
            ->assertJsonPath('data.crm.activities.overdue', 1);
        $this->dashboard(['location_id' => $ctg])->assertJsonPath('data.crm.open.value.amount', '3000.00')->assertJsonPath('data.crm.activities.overdue', 0);

        // Without View Leads or View Activities, those figures are not theirs - nor the Overview's overdue activities; their own opportunities only.
        Sanctum::actingAs($seller);
        $this->dashboard()->assertJsonPath('data.crm.leads', null)->assertJsonPath('data.crm.activities', null)->assertJsonPath('data.crm.open.count', 1);
        $this->getJson('/api/v1/crm/overview')->assertOk()->assertJsonPath('data.overdue_activities', null);

        // Without View Opportunities, no CRM figures.
        Sanctum::actingAs($this->member(['crm.leads.view', 'crm.activities.view']));
        $this->dashboard()->assertJsonPath('data.crm', null);

        // Working with opportunities only: no lead figures. CRM off: none at all.
        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'opportunities'])->assertOk();
        $this->dashboard()->assertJsonPath('data.crm.leads', null)->assertJsonPath('data.crm.open.count', 2);
        $this->postJson('/api/v1/apps/crm/deactivate')->assertOk();
        $this->dashboard()->assertJsonPath('data.crm', null);
    }
}
