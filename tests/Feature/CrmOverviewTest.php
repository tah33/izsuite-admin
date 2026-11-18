<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase G1: the CRM Overview (sections 17.3 and 17.4) - its lead,
 * opportunity and activity figures, the period that counts new leads, the
 * conversion rate and the won and lost, the filters, what each user sees,
 * and the closing-soon period in Settings → CRM.
 */
class CrmOverviewTest extends TestCase
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

    private function overview(array $query = []): TestResponse
    {
        return $this->getJson('/api/v1/crm/overview?'.http_build_query($query));
    }

    /** A lead added on $addedOn (UTC), with $status. */
    private function lead(string $name, string $status, string $addedOn = '2026-11-10 06:00:00', array $fields = []): int
    {
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');
        $id     = $this->postJson('/api/v1/crm/leads', ['name' => $name, 'phone' => '+880 1711-000000', 'source_id' => $source, ...$fields])->assertCreated()->json('data.id');

        CrmLead::query()->whereKey($id)->update(['status' => $status, 'created_at' => $addedOn]);

        return $id;
    }

    private function opportunity(string $stage, string $value, string $closeOn, array $fields = []): int
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => "Uddin Traders - {$stage} {$value}", 'contact_id' => $this->contact->id, 'stage_id' => $this->stages[$stage],
            'expected_value' => $value, 'currency' => 'USD', 'expected_close_date' => $closeOn, ...$fields,
        ])->assertCreated()->json('data.id');
    }

    /** An activity for $opportunity due at $dueOn $dueTime, in Dhaka. */
    private function activity(int $opportunity, string $dueOn, string $dueTime): void
    {
        $type = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->firstWhere('key', 'call')['id'];

        $this->postJson('/api/v1/crm/activities', ['opportunity_id' => $opportunity, 'type_id' => $type, 'subject' => "Call due {$dueOn}", 'due_on' => $dueOn, 'due_time' => $dueTime])->assertCreated();
    }

    public function test_it_counts_leads_opportunities_and_activities_for_the_period_and_now(): void
    {
        $this->lead('Older', CrmLead::ASSIGNED, '2026-10-20 06:00:00');
        $this->lead('Waiting', CrmLead::NEW);
        $this->lead('Ready', CrmLead::QUALIFIED);
        $this->lead('Done', CrmLead::CONVERTED);
        // Added at 18:30 UTC on 31 October: 1 November in Dhaka, this month.
        $this->lead('Late night', CrmLead::CONTACTED, '2026-10-31 18:30:00');

        $soon = $this->opportunity('Proposal', '1000', '2026-11-30');
        $this->opportunity('New', '2000', '2027-03-01');
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Qualified', '500', '2026-12-01').'/win', ['closed_on' => '2026-11-10'])->assertOk();
        $reason = collect($this->getJson('/api/v1/crm/opportunities/options')->json('data.lost_reasons'))->first()['id'];
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Qualified', '300', '2026-12-01').'/lose', ['closed_on' => '2026-11-12', 'lost_reason_id' => $reason])->assertOk();
        // Won last month, and an archived one: neither counts.
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Qualified', '700', '2026-12-01').'/win', ['closed_on' => '2026-10-15'])->assertOk();
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Qualified', '900', '2026-11-25').'/archive')->assertOk();

        $this->activity($soon, '2026-11-17', '10:00');
        $this->activity($soon, '2026-11-19', '10:00');

        $this->overview()->assertOk()
            ->assertJsonPath('data.from', '2026-11-01')
            ->assertJsonPath('data.to', '2026-11-30')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.closing_soon_days', 30)
            // Phase G3b: one in the queue; and the four open ones - none ever contacted - added more than two days ago.
            ->assertJsonPath('data.leads', ['new' => 4, 'awaiting' => 2, 'qualified' => 1, 'converted' => 1, 'conversion_rate' => 25, 'unassigned' => 1, 'uncontacted' => 4])
            ->assertJsonPath('data.open', ['count' => 2, 'value' => ['amount' => '3000.00', 'unrated' => 0], 'weighted' => ['amount' => '700.00', 'unrated' => 0]])
            ->assertJsonPath('data.closing_soon', ['count' => 1, 'value' => ['amount' => '1000.00', 'unrated' => 0]])
            ->assertJsonPath('data.won', ['count' => 1, 'value' => ['amount' => '500.00', 'unrated' => 0]])
            ->assertJsonPath('data.lost', ['count' => 1, 'value' => ['amount' => '300.00', 'unrated' => 0]])
            ->assertJsonPath('data.overdue_activities', 1);

        // Last month's period: its lead, and the one won then; what stands now is the same.
        $this->overview(['date_from' => '2026-10-01', 'date_to' => '2026-10-31'])->assertOk()
            ->assertJsonPath('data.leads.new', 1)
            ->assertJsonPath('data.leads.conversion_rate', 0)
            ->assertJsonPath('data.won.count', 1)
            ->assertJsonPath('data.won.value.amount', '700.00')
            ->assertJsonPath('data.lost.count', 0)
            ->assertJsonPath('data.open.count', 2);

        // No lead added in a period, no rate.
        $this->overview(['date_from' => '2026-09-01', 'date_to' => '2026-09-30'])->assertOk()->assertJsonPath('data.leads.conversion_rate', null);
    }

    public function test_the_filters_narrow_the_figures(): void
    {
        $other  = User::factory()->create(['first_name' => 'Karim', 'last_name' => 'Ali']);
        $role   = $this->business->roles()->create(['name' => 'Sellers', 'permissions' => ['crm.opportunities.view', 'crm.opportunities.manage', 'crm.leads.view', 'contacts.view']]);
        $this->business->members()->create(['user_id' => $other->id, 'status' => 'active', 'role_id' => $role->id]);

        $this->opportunity('Proposal', '1000', '2026-11-30');
        $this->opportunity('New', '2000', '2027-03-01', ['owner_id' => $other->id]);
        $this->lead('Mine', CrmLead::NEW);
        $this->lead('Theirs', CrmLead::NEW, fields: ['owner_id' => $other->id]);

        $this->overview(['owner_id' => $other->id])->assertOk()
            ->assertJsonPath('data.open.count', 1)
            ->assertJsonPath('data.open.value.amount', '2000.00')
            ->assertJsonPath('data.leads.new', 1);

        // Pipeline and stage narrow the opportunities only.
        $this->overview(['stage_id' => $this->stages['Proposal']])->assertOk()
            ->assertJsonPath('data.open.count', 1)
            ->assertJsonPath('data.open.weighted.amount', '500.00')
            ->assertJsonPath('data.leads.new', 2);

        // In the currency chosen, its own values.
        $this->overview(['currency' => 'USD'])->assertOk()->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.open.value.amount', '3000.00');
        $this->overview(['currency' => 'XYZ'])->assertUnprocessable()->assertJsonValidationErrors('currency');
    }

    public function test_each_sees_only_their_own_and_lead_figures_need_leads(): void
    {
        $member = function (array $permissions): User {
            $user = User::factory()->create();
            $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
            $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id]);

            return $user;
        };
        $seller = $member(['crm.opportunities.view', 'crm.leads.view', 'contacts.view']);

        $this->opportunity('Proposal', '1000', '2026-11-30');
        $this->opportunity('New', '2000', '2027-03-01', ['owner_id' => $seller->id]);

        Sanctum::actingAs($seller);
        $this->overview()->assertOk()->assertJsonPath('data.open.count', 1)->assertJsonPath('data.open.value.amount', '2000.00')->assertJsonPath('data.leads.new', 0);

        Sanctum::actingAs($member(['crm.opportunities.view', 'contacts.view']));
        $this->overview()->assertOk()->assertJsonPath('data.leads', null);

        Sanctum::actingAs($member(['crm.leads.view']));
        $this->overview()->assertForbidden();

        // Working with opportunities only, there are no lead figures.
        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'opportunities'])->assertOk();
        $this->overview()->assertOk()->assertJsonPath('data.leads', null)->assertJsonPath('data.open.count', 2);
    }

    public function test_the_closing_soon_period_is_set_in_settings(): void
    {
        $this->opportunity('Proposal', '1000', '2026-11-30');
        $this->opportunity('New', '2000', '2027-03-01');
        // Its closing day is past: overdue, not closing soon.
        $this->opportunity('New', '400', '2026-11-17');

        $this->overview()->assertJsonPath('data.closing_soon.count', 1);

        $this->putJson('/api/v1/business-settings/crm', ['closing_soon_days' => 0])->assertUnprocessable()->assertJsonValidationErrors('closing_soon_days');
        $this->putJson('/api/v1/business-settings/crm', ['closing_soon_days' => 366])->assertUnprocessable()->assertJsonValidationErrors('closing_soon_days');
        $this->putJson('/api/v1/business-settings/crm', ['closing_soon_days' => 120])->assertOk()->assertJsonPath('data.closing_soon_days', 120);

        $this->overview()->assertJsonPath('data.closing_soon_days', 120)
            ->assertJsonPath('data.closing_soon.count', 2)
            ->assertJsonPath('data.closing_soon.value.amount', '3000.00');
    }
}
