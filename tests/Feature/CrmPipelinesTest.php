<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase D3b1: several pipelines (sections 17.13, 17.26 and 17.30) -
 * added in Settings → CRM with the starting stages, one the default, others
 * switched off or deleted when no opportunity is or was in them - and
 * opportunities added, converted and moved into the pipelines switched on.
 */
class CrmPipelinesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->contact = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
    }

    /** Settings → CRM's pipelines by name. */
    private function pipelines(): array
    {
        return collect($this->getJson('/api/v1/business-settings/crm')->assertOk()->json('data.pipelines'))->keyBy('name')->all();
    }

    /** The id of $pipeline's stage called $stage. */
    private function stage(string $pipeline, string $stage): int
    {
        return collect($this->pipelines()[$pipeline]['stages'])->firstWhere('name', $stage)['id'];
    }

    private function addPipeline(string $name): TestResponse
    {
        return $this->postJson('/api/v1/business-settings/crm/pipelines', ['name' => $name]);
    }

    /** Saves $name as it is, switched on or off. */
    private function switchPipeline(string $name, bool $on): TestResponse
    {
        $pipeline = $this->pipelines()[$name];

        return $this->putJson("/api/v1/business-settings/crm/pipelines/{$pipeline['id']}", ['name' => $name, 'is_active' => $on, 'stages' => $pipeline['stages']]);
    }

    private function add(int $stageId): TestResponse
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - shelving', 'contact_id' => $this->contact->id, 'stage_id' => $stageId,
            'expected_value' => '2000', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20',
        ]);
    }

    private function move(int $id, int $stageId): TestResponse
    {
        return $this->postJson("/api/v1/crm/opportunities/{$id}/stage", ['stage_id' => $stageId]);
    }

    public function test_a_business_adds_pipelines_with_the_starting_stages_up_to_its_limit(): void
    {
        $first = $this->pipelines()['Sales Pipeline'];
        $this->assertSame([true, true, false], [$first['is_default'], $first['is_active'], $first['in_use']]);

        $this->addPipeline('Projects')->assertCreated()
            ->assertJsonPath('message', 'Pipeline added.')
            ->assertJsonPath('data.is_default', false)
            ->assertJsonPath('data.is_active', true);
        $this->assertSame(['New', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'], array_column($this->pipelines()['Projects']['stages'], 'name'));
        $this->assertDatabaseHas('activity_logs', ['description' => 'Added the pipeline "Projects"']);

        $this->addPipeline('Projects')->assertUnprocessable()->assertJsonValidationErrors(['name' => 'The business has a pipeline with this name already.']);

        foreach (range(3, 20) as $n) {
            $this->addPipeline("Pipeline {$n}")->assertCreated();
        }

        $this->addPipeline('One Too Many')->assertUnprocessable()->assertJsonValidationErrors(['name' => 'A business can have up to 20 pipelines.']);
    }

    public function test_the_default_pipeline_stays_switched_on_and_is_not_deleted(): void
    {
        $this->addPipeline('Projects')->assertCreated();
        $sales    = $this->pipelines()['Sales Pipeline']['id'];
        $projects = $this->pipelines()['Projects']['id'];

        $this->switchPipeline('Sales Pipeline', false)->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active' => 'The default pipeline stays switched on: make another one the default first.']);
        $this->deleteJson("/api/v1/business-settings/crm/pipelines/{$sales}")->assertStatus(409)
            ->assertJsonPath('message', 'The default pipeline cannot be deleted: make another one the default first.');

        $this->switchPipeline('Projects', false)->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson("/api/v1/business-settings/crm/pipelines/{$projects}/default")->assertStatus(409)
            ->assertJsonPath('message', 'Switch this pipeline on before making it the default.');

        $this->switchPipeline('Projects', true)->assertOk();
        $this->postJson("/api/v1/business-settings/crm/pipelines/{$projects}/default")->assertOk()
            ->assertJsonPath('message', 'Projects is the default pipeline.')
            ->assertJsonPath('data.is_default', true);
        $this->assertSame([false, true], [$this->pipelines()['Sales Pipeline']['is_default'], $this->pipelines()['Projects']['is_default']]);

        // The one that was the default is an ordinary one now: unused, it goes, with its stages.
        $this->deleteJson("/api/v1/business-settings/crm/pipelines/{$sales}")->assertOk()->assertJsonPath('message', 'Pipeline deleted.');
        $this->assertSame(['Projects'], array_keys($this->pipelines()));
        $this->assertDatabaseMissing('crm_stages', ['crm_pipeline_id' => $sales]);
    }

    public function test_a_pipeline_opportunities_are_or_were_in_stays(): void
    {
        $this->addPipeline('Projects')->assertCreated();
        $projects = $this->pipelines()['Projects']['id'];

        $id = $this->add($this->stage('Projects', 'New'))->assertCreated()
            ->assertJsonPath('data.pipeline.name', 'Projects')
            ->assertJsonPath('data.stage.name', 'New')
            ->json('data.id');
        $this->assertTrue($this->pipelines()['Projects']['in_use']);

        $refused = 'Opportunities are or were in this pipeline, so it stays. Switch it off instead.';
        $this->deleteJson("/api/v1/business-settings/crm/pipelines/{$projects}")->assertStatus(409)->assertJsonPath('message', $refused);

        // Moved out, it was there: the pipeline keeps its history.
        $this->move($id, $this->stage('Sales Pipeline', 'Qualified'))->assertOk()->assertJsonPath('data.pipeline.name', 'Sales Pipeline');
        $this->deleteJson("/api/v1/business-settings/crm/pipelines/{$projects}")->assertStatus(409)->assertJsonPath('message', $refused);
    }

    public function test_opportunities_enter_only_pipelines_switched_on_and_carry_on_in_one_switched_off(): void
    {
        $this->addPipeline('Projects')->assertCreated();
        $id = $this->add($this->stage('Sales Pipeline', 'New'))->assertCreated()->json('data.id');

        $this->switchPipeline('Projects', false)->assertOk();
        $this->assertFalse(collect($this->getJson('/api/v1/crm/opportunities/options')->json('data.pipelines'))->firstWhere('name', 'Projects')['is_active']);

        $refused = ['stage_id' => 'Choose an open stage of a pipeline switched on.'];
        $this->add($this->stage('Projects', 'New'))->assertUnprocessable()->assertJsonValidationErrors($refused);
        $this->move($id, $this->stage('Projects', 'Qualified'))->assertUnprocessable()->assertJsonValidationErrors($refused);

        // Switched on, it moves there - its history naming the stage of the pipeline it came from.
        $this->switchPipeline('Projects', true)->assertOk();
        $this->move($id, $this->stage('Projects', 'Qualified'))->assertOk()
            ->assertJsonPath('message', 'Moved to Qualified in Projects.')
            ->assertJsonPath('data.pipeline.name', 'Projects')
            ->assertJsonPath('data.stage.name', 'Qualified')
            ->assertJsonPath('data.probability', 25)
            ->assertJsonPath('data.visits.0.stage', 'New (Sales Pipeline)')
            ->assertJsonPath('data.visits.1.stage', 'Qualified');

        $log = DB::table('activity_logs')->where('description', 'Moved opportunity "Uddin Traders - shelving" from New (Sales Pipeline) to Qualified (Projects)')->first();
        $this->assertNotNull($log);
        $this->assertSame(
            ['setting' => 'pipeline', 'from' => 'Sales Pipeline', 'to' => 'Projects'],
            json_decode($log->properties, true)['changes'][0],
        );

        // Switched off with it inside: it carries on there, moved, won and reopened.
        $this->switchPipeline('Projects', false)->assertOk();
        $this->move($id, $this->stage('Projects', 'Proposal'))->assertOk()->assertJsonPath('message', 'Moved to Proposal.')->assertJsonPath('data.stage.name', 'Proposal');
        $this->postJson("/api/v1/crm/opportunities/{$id}/win", ['closed_on' => now()->toDateString()])->assertOk()->assertJsonPath('data.stage.name', 'Won');
        $this->postJson("/api/v1/crm/opportunities/{$id}/reopen")->assertOk()->assertJsonPath('data.pipeline.name', 'Projects');
    }

    public function test_a_lead_converts_into_any_pipeline_switched_on(): void
    {
        $this->addPipeline('Projects')->assertCreated();
        $this->addPipeline('Retail')->assertCreated();
        $this->switchPipeline('Retail', false)->assertOk();

        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');
        $lead   = $this->postJson('/api/v1/crm/leads', ['name' => 'Rahim Uddin', 'phone' => '+880 1711-000000', 'source_id' => $source, 'customer_need' => 'Shelving'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();

        $preview = $this->getJson("/api/v1/crm/leads/{$lead}/conversion")->assertOk()->json('data');
        $this->assertSame(['Sales Pipeline', 'Projects'], array_column($preview['pipelines'], 'name'));
        $this->assertSame($this->stage('Sales Pipeline', 'New'), $preview['defaults']['stage_id']);

        $convert = fn (int $stage) => $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => $this->contact->id, 'opportunity' => [
            'name' => 'Uddin Traders - shelving', 'stage_id' => $stage, 'expected_value' => '1500', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20',
        ]]);

        $convert($this->stage('Retail', 'New'))->assertUnprocessable()->assertJsonValidationErrors(['opportunity.stage_id' => 'Choose an open stage of a pipeline switched on.']);
        $opportunity = $convert($this->stage('Projects', 'Proposal'))->assertOk()->json('data.conversion.opportunity.id');

        $this->getJson("/api/v1/crm/opportunities/{$opportunity}")->assertOk()
            ->assertJsonPath('data.pipeline.name', 'Projects')
            ->assertJsonPath('data.stage.name', 'Proposal');
    }

    public function test_the_list_filters_by_pipeline_and_names_each_ones(): void
    {
        $this->addPipeline('Projects')->assertCreated();
        $this->add($this->stage('Sales Pipeline', 'New'))->assertCreated();
        $this->add($this->stage('Projects', 'New'))->assertCreated();

        $projects = $this->pipelines()['Projects']['id'];
        $rows     = $this->getJson("/api/v1/crm/opportunities?pipeline_id={$projects}")->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('Projects', $rows[0]['pipeline']);
        $this->assertCount(2, $this->getJson('/api/v1/crm/opportunities')->json('data'));
    }

    public function test_managing_pipelines_needs_its_own_permission(): void
    {
        $member = function (array $permissions): User {
            $user = User::factory()->create();
            $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
            $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id]);

            return $user;
        };
        $pipeline = $this->pipelines()['Sales Pipeline'];

        Sanctum::actingAs($member(['settings.view', 'crm.settings.manage']));
        $this->addPipeline('Projects')->assertForbidden();
        $this->putJson("/api/v1/business-settings/crm/pipelines/{$pipeline['id']}", ['name' => 'Sales', 'stages' => $pipeline['stages']])->assertForbidden();

        Sanctum::actingAs($member(['settings.view', 'crm.settings.pipelines']));
        $this->addPipeline('Projects')->assertCreated();
        $this->putJson("/api/v1/business-settings/crm/pipelines/{$pipeline['id']}", ['name' => 'Sales', 'stages' => $pipeline['stages']])->assertOk();
    }
}
