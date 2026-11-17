<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Api\Crm\CrmOpportunityViews;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase D4: the Opportunities page's board - one pipeline's stages as
 * columns, open opportunities by expected closing date and those won or lost
 * in the last 30 days, each column's count and worth in the base currency -
 * and its calendar, a month of the list by expected closing day. Monday 16
 * November 2026; the base currency is USD, and EUR is 1.08 of it.
 */
class CrmOpportunityViewsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $contact;

    /** @var array<string, int> the stages by name */
    private array $stages;

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
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR', 'GBP']])->save();
        $this->business->exchangeRates()->create(['currency' => 'EUR', 'base_currency' => 'USD', 'rate' => '1.08', 'effective_date' => '2026-10-01']);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->contact = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $this->stages  = collect($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages'))->pluck('id', 'name')->all();
    }

    /** An opportunity at New, in EUR, closing on 20 December unless $fields say otherwise: its id. */
    private function add(array $fields = []): int
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - shelving', 'contact_id' => $this->contact->id, 'stage_id' => $this->stages['New'],
            'expected_value' => '2000', 'currency' => 'EUR', 'expected_close_date' => '2026-12-20', ...$fields,
        ])->assertCreated()->json('data.id');
    }

    private function board(string $query = ''): TestResponse
    {
        return $this->getJson('/api/v1/crm/opportunities/board'.$query);
    }

    /** Each column as [stage, count, total, unrated, its cards' names]. */
    private function columns(TestResponse $board): array
    {
        return array_map(fn (array $column) => [$column['stage']['name'], $column['count'], $column['total'], $column['unrated'], array_column($column['cards'], 'name')], $board->json('data.columns'));
    }

    public function test_the_board_holds_a_pipelines_open_and_lately_closed_opportunities_by_stage(): void
    {
        $reason = $this->getJson('/api/v1/crm/opportunities/options')->json('data.lost_reasons.0.id');

        $this->add(['name' => 'A', 'expected_value' => '1000', 'currency' => 'USD']);
        $this->add(['name' => 'B', 'expected_value' => '500', 'expected_close_date' => '2026-12-05']);
        $this->add(['name' => 'C', 'stage_id' => $this->stages['Proposal'], 'expected_value' => '2000', 'currency' => 'USD']);
        $this->add(['name' => 'H', 'stage_id' => $this->stages['Negotiation'], 'expected_value' => '400', 'currency' => 'GBP']);
        $this->postJson('/api/v1/crm/opportunities/'.$this->add(['name' => 'D']).'/win', ['closed_on' => '2026-11-10', 'value' => '3000'])->assertOk();
        $this->postJson('/api/v1/crm/opportunities/'.$this->add(['name' => 'E']).'/win', ['closed_on' => '2026-10-01'])->assertOk();
        $this->postJson('/api/v1/crm/opportunities/'.$this->add(['name' => 'F']).'/lose', ['closed_on' => '2026-11-15', 'lost_reason_id' => $reason])->assertOk();
        $this->postJson('/api/v1/crm/opportunities/'.$this->add(['name' => 'G']).'/archive')->assertOk();

        // Weighted while open: A 100.00, B 50 EUR as 54.00, C 1000.00. GBP has no rate today.
        // Won and lost count whole: D 3000 EUR as 3240.00, F 2000 EUR as 2160.00. E was won 46 days ago; G is archived.
        $board = $this->board()->assertOk()
            ->assertJsonPath('data.pipeline.name', 'Sales Pipeline')
            ->assertJsonPath('data.pipelines', [['id' => $this->board()->json('data.pipeline.id'), 'name' => 'Sales Pipeline', 'is_active' => true]]);

        $this->assertSame([
            ['New', 2, '154.00', 0, ['B', 'A']],
            ['Qualified', 0, '0.00', 0, []],
            ['Proposal', 1, '1000.00', 0, ['C']],
            ['Negotiation', 1, '0.00', 1, ['H']],
            ['Won', 1, '3240.00', 0, ['D']],
            ['Lost', 1, '2160.00', 0, ['F']],
        ], $this->columns($board));

        $this->assertSame(
            ['name' => 'B', 'contact' => 'Uddin Traders', 'expected_value' => '500.00', 'currency' => 'EUR', 'probability' => 10, 'expected_close_date' => '2026-12-05', 'overdue' => false, 'stale' => false, 'days_at_stage' => 0, 'owner' => $this->user->name, 'closed_on' => null],
            array_diff_key($board->json('data.columns.0.cards.0'), ['id' => true]),
        );
        $this->assertSame(['open', 'open', 'open', 'open', 'won', 'lost'], array_column(array_column($board->json('data.columns'), 'stage'), 'category'));
        $this->assertSame('2026-11-10', $board->json('data.columns.4.cards.0.closed_on'));
    }

    public function test_a_stage_switched_off_shows_while_it_holds_one_and_another_pipeline_has_its_own_board(): void
    {
        $this->add(['name' => 'A', 'stage_id' => $this->stages['Qualified']]);

        // Qualified switched off still shows what is at it; Demo, switched off and empty, does not.
        $pipeline = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0');
        $stages   = array_map(fn (array $stage) => $stage['name'] === 'Qualified' ? [...$stage, 'is_active' => false] : $stage, $pipeline['stages']);
        $stages[] = ['id' => null, 'name' => 'Demo', 'probability' => 30, 'category' => 'open', 'is_active' => false];
        $this->putJson("/api/v1/business-settings/crm/pipelines/{$pipeline['id']}", [...$pipeline, 'stages' => $stages])->assertOk();

        $board = $this->board()->assertOk();
        $this->assertSame(['New', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'], array_column(array_column($board->json('data.columns'), 'stage'), 'name'));
        $this->assertSame([false, ['A']], [$board->json('data.columns.1.stage.is_active'), array_column($board->json('data.columns.1.cards'), 'name')]);

        // Another pipeline - switched off, even - has its own board; another business's is not found.
        $wholesale = $this->postJson('/api/v1/business-settings/crm/pipelines', ['name' => 'Wholesale'])->assertCreated()->json('data');
        $this->putJson("/api/v1/business-settings/crm/pipelines/{$wholesale['id']}", [...$wholesale, 'is_active' => false])->assertOk();

        $other = $this->board("?pipeline_id={$wholesale['id']}")->assertOk()->assertJsonPath('data.pipeline.name', 'Wholesale');
        $this->assertSame([[true, 'Sales Pipeline'], [false, 'Wholesale']], array_map(fn (array $row) => [$row['is_active'], $row['name']], $other->json('data.pipelines')));
        $this->assertSame([0, 0, 0, 0, 0, 0], array_column($other->json('data.columns'), 'count'));

        $theirs = $this->businessOf(User::factory()->create())->crmPipelines()->create(['name' => 'Theirs', 'position' => 1, 'is_active' => true]);
        $this->board("?pipeline_id={$theirs->id}")->assertNotFound();
    }

    public function test_the_board_shows_whom_the_user_sees_searched_and_by_owner_up_to_its_column_limit(): void
    {
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Seller', 'permissions' => ['crm.opportunities.view', 'crm.opportunities.manage']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        $this->add(['name' => 'Theirs - racking', 'owner_id' => $member->id, 'expected_close_date' => '2026-12-31']);
        foreach (range(1, CrmOpportunityViews::CARDS_PER_COLUMN) as $number) {
            $this->add(['name' => "Mine {$number}", 'expected_close_date' => '2026-12-'.str_pad((string) min($number, 28), 2, '0', STR_PAD_LEFT)]);
        }

        // The owner sees all 51, the column showing the 50 closing soonest.
        $new = $this->board()->json('data.columns.0');
        $this->assertSame([51, CrmOpportunityViews::CARDS_PER_COLUMN, 'Mine 1'], [$new['count'], count($new['cards']), $new['cards'][0]['name']]);
        $this->assertNotContains('Theirs - racking', array_column($new['cards'], 'name'));

        $this->assertSame(['Theirs - racking'], array_column($this->board('?search=racking')->json('data.columns.0.cards'), 'name'));
        $this->assertSame(1, $this->board("?owner_id={$member->id}")->json('data.columns.0.count'));
        $this->assertSame(50, $this->board('?owner_id=me')->json('data.columns.0.count'));

        // A member who sees only their own sees theirs.
        Sanctum::actingAs($member);
        $this->assertSame([1, ['Theirs - racking']], [$this->board()->json('data.columns.0.count'), array_column($this->board()->json('data.columns.0.cards'), 'name')]);
    }

    public function test_the_calendar_is_a_month_of_the_list_by_expected_closing_day(): void
    {
        $this->add(['name' => 'Past', 'expected_close_date' => '2026-11-10']);
        $this->add(['name' => 'Coming', 'expected_close_date' => '2026-11-20', 'stage_id' => $this->stages['Proposal']]);
        $this->add(['name' => 'December', 'expected_close_date' => '2026-12-05']);
        $won = $this->add(['name' => 'Won', 'expected_close_date' => '2026-11-25']);
        $this->postJson("/api/v1/crm/opportunities/{$won}/win", ['closed_on' => '2026-11-16'])->assertOk();

        // This month to start, the open ones as the list: on their day, one passed marked overdue.
        $month = $this->getJson('/api/v1/crm/opportunities/calendar')->assertOk()
            ->assertJsonPath('data.month', '2026-11')
            ->assertJsonPath('data.today', '2026-11-16')
            ->assertJsonPath('data.week_start', 1)
            ->assertJsonPath('data.count', 2);
        $this->assertSame([['Past', '2026-11-10', 'New', true], ['Coming', '2026-11-20', 'Proposal', false]], array_map(
            fn (array $entry) => [$entry['name'], $entry['expected_close_date'], $entry['stage'], $entry['overdue']],
            $month->json('data.entries'),
        ));

        $this->assertSame(['Won'], array_column($this->getJson('/api/v1/crm/opportunities/calendar?status=won')->json('data.entries'), 'name'));
        $this->assertSame(['Past', 'Coming', 'Won'], array_column($this->getJson('/api/v1/crm/opportunities/calendar?status=all')->json('data.entries'), 'name'));
        $this->assertSame(['December'], array_column($this->getJson('/api/v1/crm/opportunities/calendar?month=2026-12')->json('data.entries'), 'name'));
        $this->getJson('/api/v1/crm/opportunities/calendar?month=2026-13')->assertUnprocessable()->assertJsonValidationErrors('month');

        // The business's week start.
        $this->business->forceFill(['week_start' => 'sunday'])->save();
        $this->getJson('/api/v1/crm/opportunities/calendar')->assertJsonPath('data.week_start', 0);
    }
}
