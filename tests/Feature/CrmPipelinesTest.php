<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\SalesTeam;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase D3b: several pipelines - one added with the starter stages,
 * each switched on or off and for all sales teams and locations or the ones
 * chosen, one deleted while no opportunity was ever in it - with one for
 * everyone always kept on; and opportunities, added or converted from leads,
 * going in one switched on that is for their team and location, the first
 * that fits when none is asked for. Monday 16 November 2026.
 */
class CrmPipelinesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $contact;

    private SalesTeam $team;

    private int $main;

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
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->contact = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $this->team    = SalesTeam::create(['workspace_id' => $this->business->id, 'name' => 'Wholesale Desk']);
        $this->main    = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.id');
    }

    /** Pipeline $id saved as it is, with $fields over it. */
    private function save(int $id, array $fields): TestResponse
    {
        $pipeline = collect($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines'))->firstWhere('id', $id);

        return $this->putJson("/api/v1/business-settings/crm/pipelines/{$id}", [...$pipeline, ...$fields]);
    }

    private function add(array $fields): TestResponse
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - racking', 'contact_id' => $this->contact->id, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20', ...$fields,
        ]);
    }

    public function test_a_pipeline_is_added_for_chosen_teams_and_one_for_everyone_stays_on(): void
    {
        $wholesale = $this->postJson('/api/v1/business-settings/crm/pipelines', ['name' => 'Wholesale', 'team_ids' => [$this->team->id]])
            ->assertCreated()
            ->assertJsonPath('message', 'Pipeline "Wholesale" added.')
            ->assertJsonPath('data.team_ids', [$this->team->id])
            ->assertJsonPath('data.removable', true)
            ->json('data');

        $this->assertSame(['New', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'], array_column($wholesale['stages'], 'name'));
        $this->postJson('/api/v1/business-settings/crm/pipelines', ['name' => 'Wholesale'])->assertUnprocessable()->assertJsonValidationErrors('name');

        // The only pipeline for everyone is neither switched off nor narrowed.
        $this->save($this->main, ['is_active' => false])->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->save($this->main, ['team_ids' => [$this->team->id]])->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->deleteJson("/api/v1/business-settings/crm/pipelines/{$this->main}")->assertUnprocessable();

        // Wholesale is switched off and on, and deleted while nothing was in it.
        $this->save($wholesale['id'], ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->save($wholesale['id'], ['is_active' => true])->assertOk();

        $this->add(['pipeline_id' => $wholesale['id'], 'stage_id' => $wholesale['stages'][0]['id'], 'sales_team_id' => $this->team->id])->assertCreated();
        $this->assertSame(['Sales Pipeline' => true, 'Wholesale' => false], collect($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines'))->pluck('removable', 'name')->all());
        $this->save($wholesale['id'], [])->assertOk()->assertJsonPath('data.removable', false);
        $this->deleteJson("/api/v1/business-settings/crm/pipelines/{$wholesale['id']}")->assertStatus(409)
            ->assertJsonPath('message', 'Opportunities are or were in "Wholesale", so it stays: switch it off instead.');

        $spare = $this->postJson('/api/v1/business-settings/crm/pipelines', ['name' => 'Spare'])->assertCreated()->json('data.id');
        $this->deleteJson("/api/v1/business-settings/crm/pipelines/{$spare}")->assertOk()->assertJsonPath('message', 'Pipeline deleted.');
        $this->assertSame(['Sales Pipeline', 'Wholesale'], array_column($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines'), 'name'));
    }

    public function test_an_opportunity_goes_in_a_pipeline_for_its_team_and_location(): void
    {
        $wholesale = $this->postJson('/api/v1/business-settings/crm/pipelines', ['name' => 'Wholesale', 'team_ids' => [$this->team->id]])->assertCreated()->json('data');
        $start     = $wholesale['stages'][0]['id'];

        $options = $this->getJson('/api/v1/crm/opportunities/options')->assertOk()->json('data');
        $this->assertSame(['Sales Pipeline', 'Wholesale'], array_column($options['pipelines'], 'name'));
        $this->assertSame(['New', $this->main], [$options['all_stages'][0]['name'], $options['all_stages'][0]['pipeline_id']]);
        $this->assertSame(['New', $wholesale['id']], [$options['all_stages'][6]['name'], $options['all_stages'][6]['pipeline_id']]);

        // Without a team, Wholesale is not for it; with the team it is.
        $this->add(['pipeline_id' => $wholesale['id'], 'stage_id' => $start])->assertUnprocessable()->assertJsonValidationErrors('pipeline_id');
        $id = $this->add(['pipeline_id' => $wholesale['id'], 'stage_id' => $start, 'sales_team_id' => $this->team->id])->assertCreated()->json('data.id');

        // Another pipeline's stage is not one of its own; left out, the pipeline is the first that fits.
        $mainNew = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $this->add(['pipeline_id' => $wholesale['id'], 'stage_id' => $mainNew, 'sales_team_id' => $this->team->id])->assertUnprocessable()->assertJsonValidationErrors('stage_id');
        $this->add(['stage_id' => $mainNew, 'sales_team_id' => $this->team->id])->assertCreated();

        // The list takes one pipeline's; a row and the record name theirs.
        $this->assertSame([[$id, 'Wholesale']], array_map(fn (array $row) => [$row['id'], $row['pipeline']], $this->getJson("/api/v1/crm/opportunities?pipeline_id={$wholesale['id']}")->json('data')));
        $this->getJson("/api/v1/crm/opportunities/{$id}")->assertJsonPath('data.pipeline', ['id' => $wholesale['id'], 'name' => 'Wholesale']);
        $this->assertSame(2, $this->getJson('/api/v1/crm/opportunities')->json('pagination.total'));

        // A lead of the team converts into Wholesale.
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');
        $lead   = $this->postJson('/api/v1/crm/leads', ['name' => 'Karim Ahmed', 'business_name' => 'Karim Stores', 'phone' => '+8801711000000', 'source_id' => $source, 'sales_team_id' => $this->team->id, 'customer_need' => 'Racking for a warehouse'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();
        $preview = $this->getJson("/api/v1/crm/leads/{$lead}/conversion")->assertOk()->json('data');
        $this->assertSame(['Sales Pipeline', 'Wholesale'], array_column($preview['pipelines'], 'name'));
        $this->assertSame(['id', 'name', 'stages'], array_keys($preview['pipelines'][1]));

        $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => null, 'opportunity' => [
            ...$preview['defaults'], 'pipeline_id' => $wholesale['id'], 'stage_id' => $start, 'expected_value' => '900', 'expected_close_date' => '2026-12-31',
        ]])->assertOk();
        $this->assertSame(2, $this->getJson("/api/v1/crm/opportunities?pipeline_id={$wholesale['id']}")->json('pagination.total'));
    }

    public function test_a_pipeline_for_a_location_and_one_switched_off_are_kept_to(): void
    {
        $dhaka  = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK'])->id;
        $ctg    = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG'])->id;
        $theirs = SalesTeam::create(['workspace_id' => $this->businessOf(User::factory()->create())->id, 'name' => 'Theirs']);

        $this->postJson('/api/v1/business-settings/crm/pipelines', ['name' => 'Projects', 'team_ids' => [$theirs->id]])->assertUnprocessable()->assertJsonValidationErrors('team_ids.0');
        $projects = $this->postJson('/api/v1/business-settings/crm/pipelines', ['name' => 'Projects', 'location_ids' => [$dhaka]])->assertCreated()->json('data');
        $start    = $projects['stages'][0]['id'];

        $this->add(['pipeline_id' => $projects['id'], 'stage_id' => $start, 'location_id' => $ctg])->assertUnprocessable()->assertJsonValidationErrors('pipeline_id');
        $id = $this->add(['pipeline_id' => $projects['id'], 'stage_id' => $start, 'location_id' => $dhaka])->assertCreated()->json('data.id');

        // Switched off, it is neither offered nor taken; what is in it stays, and still moves between its stages.
        $this->save($projects['id'], ['is_active' => false])->assertOk();
        $this->assertSame(['Sales Pipeline'], array_column($this->getJson('/api/v1/crm/opportunities/options')->json('data.pipelines'), 'name'));
        $this->add(['pipeline_id' => $projects['id'], 'stage_id' => $start, 'location_id' => $dhaka])->assertUnprocessable()->assertJsonValidationErrors('pipeline_id');
        $this->assertSame(1, $this->getJson("/api/v1/crm/opportunities?pipeline_id={$projects['id']}")->json('pagination.total'));
        $this->postJson("/api/v1/crm/opportunities/{$id}/stage", ['stage_id' => $projects['stages'][1]['id']])->assertOk()->assertJsonPath('data.stage.name', 'Qualified');
    }
}
