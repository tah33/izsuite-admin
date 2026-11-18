<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
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
 * Doc 17 Phase G2: the Forecast (sections 17.3 and 17.14) - the open
 * opportunities' count, expected and weighted value by expected closing
 * month (Overdue, this month and the next 11, Later), or by owner, team or
 * stage, with what was won this month; within each user's own scope.
 */
class CrmForecastTest extends TestCase
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

    private function forecast(array $query = []): TestResponse
    {
        return $this->getJson('/api/v1/crm/forecast?'.http_build_query($query));
    }

    private function opportunity(string $stage, string $value, string $closeOn, array $fields = []): int
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => "Uddin Traders - {$stage} {$value}", 'contact_id' => $this->contact->id, 'stage_id' => $this->stages[$stage],
            'expected_value' => $value, 'currency' => 'USD', 'expected_close_date' => $closeOn, ...$fields,
        ])->assertCreated()->json('data.id');
    }

    /** Opportunities across the months: one overdue, this month's, January's, one later - and won this month and last. */
    private function spread(array $fields = []): void
    {
        $this->opportunity('Proposal', '1000', '2026-11-30', $fields);
        $this->opportunity('New', '2000', '2027-01-15', $fields);
        $this->opportunity('Qualified', '400', '2026-11-10', $fields);
        $this->opportunity('Negotiation', '800', '2028-01-01', $fields);
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Qualified', '500', '2026-12-01', $fields).'/win', ['closed_on' => '2026-11-12'])->assertOk();
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Qualified', '700', '2026-12-01', $fields).'/win', ['closed_on' => '2026-10-15'])->assertOk();
        $this->postJson('/api/v1/crm/opportunities/'.$this->opportunity('Qualified', '900', '2026-12-05', $fields).'/archive')->assertOk();
    }

    /** A row's figures, short: [label, count, value, weighted, won]. */
    private function row(array $row): array
    {
        return [$row['label'], $row['count'], $row['value']['amount'], $row['weighted']['amount'], $row['won']['amount']];
    }

    public function test_it_forecasts_by_closing_month_with_this_months_wins(): void
    {
        $this->spread();

        $data = $this->forecast()->assertOk()->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.group_by', 'month')->json('data');
        $rows = collect($data['rows'])->keyBy('key');

        // Overdue, November 2026 to October 2027 - empty months too - and Later.
        $this->assertSame(['overdue', '2026-11', '2026-12', '2027-01'], $rows->keys()->take(4)->all());
        $this->assertSame(['2027-10', 'later'], $rows->keys()->slice(-2)->values()->all());
        $this->assertCount(14, $rows);

        $this->assertSame(['Overdue', 1, '400.00', '100.00', '0.00'], $this->row($rows['overdue']));
        $this->assertSame(['November 2026', 1, '1000.00', '500.00', '500.00'], $this->row($rows['2026-11']));
        $this->assertSame(['December 2026', 0, '0.00', '0.00', '0.00'], $this->row($rows['2026-12']));
        $this->assertSame(['January 2027', 1, '2000.00', '200.00', '0.00'], $this->row($rows['2027-01']));
        $this->assertSame(['Later', 1, '800.00', '600.00', '0.00'], $this->row($rows['later']));
        $this->assertSame([4, '4200.00', '1400.00', '500.00'], [$data['total']['count'], $data['total']['value']['amount'], $data['total']['weighted']['amount'], $data['total']['won']['amount']]);
    }

    public function test_it_groups_by_owner_team_or_stage(): void
    {
        $other = User::factory()->create(['first_name' => 'Karim', 'last_name' => 'Ali']);
        $role  = $this->business->roles()->create(['name' => 'Sellers', 'permissions' => ['crm.opportunities.view', 'crm.opportunities.manage', 'contacts.view']]);
        $this->business->members()->create(['user_id' => $other->id, 'status' => 'active', 'role_id' => $role->id]);
        $team  = $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'Wholesale Desk', 'is_active' => true, 'member_ids' => []])->assertCreated()->json('data.id');

        $this->spread();
        $this->opportunity('Proposal', '3000', '2026-12-20', ['owner_id' => $other->id, 'sales_team_id' => $team]);

        $owners = collect($this->forecast(['group_by' => 'owner'])->assertOk()->json('data.rows'))->keyBy('label');
        $this->assertSame([1, '3000.00', '1500.00', '0.00'], array_slice($this->row($owners['Karim Ali']), 1));
        $this->assertSame([4, '4200.00', '1400.00', '500.00'], array_slice($this->row($owners[$this->user->name]), 1));

        // By team, no team last.
        $teams = $this->forecast(['group_by' => 'team'])->assertOk()->json('data.rows');
        $this->assertSame(['Wholesale Desk', 'No team'], array_column($teams, 'label'));
        $this->assertSame([1, '3000.00'], [$teams[0]['count'], $teams[0]['value']['amount']]);

        // By stage, in the pipeline's order: the won stage holds this month's win.
        $stages = $this->forecast(['group_by' => 'stage'])->assertOk()->json('data.rows');
        $this->assertSame(['New', 'Qualified', 'Proposal', 'Negotiation', 'Won'], array_column($stages, 'label'));
        $this->assertSame(['Proposal', 2, '4000.00', '2000.00', '0.00'], $this->row($stages[2]));
        $this->assertSame(['Won', 0, '0.00', '0.00', '500.00'], $this->row($stages[4]));

        // The filters narrow it, as the Overview's do.
        $this->forecast(['group_by' => 'stage', 'owner_id' => $other->id])->assertOk()->assertJsonPath('data.total.count', 1);
        $this->forecast(['group_by' => 'week'])->assertUnprocessable()->assertJsonValidationErrors('group_by');
    }

    public function test_each_sees_only_their_own(): void
    {
        $seller = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Seller', 'permissions' => ['crm.opportunities.view', 'contacts.view']]);
        $this->business->members()->create(['user_id' => $seller->id, 'status' => 'active', 'role_id' => $role->id]);

        $this->spread();
        $this->opportunity('Proposal', '3000', '2026-12-20', ['owner_id' => $seller->id]);

        Sanctum::actingAs($seller);
        $this->forecast()->assertOk()->assertJsonPath('data.total.count', 1)->assertJsonPath('data.total.value.amount', '3000.00');

        Sanctum::actingAs($this->user);
        $this->forecast()->assertOk()->assertJsonPath('data.total.count', 5);
    }
}
