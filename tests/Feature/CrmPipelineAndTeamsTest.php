<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\CrmPipeline;
use App\Models\User\CrmStage;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase A2: a business's opportunity pipeline - six stages to start,
 * saved whole from Settings > CRM with each stage keeping its id - and sales
 * teams shared by the full Sales app and CRM, with a manager. The business
 * has Sales Lite, not the full app.
 */
class CrmPipelineAndTeamsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
    }

    private function crmOn(): void
    {
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
    }

    /** @return array{id: int, name: string, stages: list<array>} the business's pipeline as Settings > CRM reads it */
    private function pipeline(): array
    {
        return $this->getJson('/api/v1/business-settings/crm')->assertOk()->json('data.pipelines.0');
    }

    /** $stages as the form sends them: id, name, probability, category, on. */
    private function save(int $id, string $name, array $stages): \Illuminate\Testing\TestResponse
    {
        return $this->putJson("/api/v1/business-settings/crm/pipelines/{$id}", ['name' => $name, 'stages' => $stages]);
    }

    public function test_a_business_starts_with_one_pipeline_of_six_stages(): void
    {
        $this->crmOn();

        $pipeline = $this->pipeline();

        $this->assertSame('Sales Pipeline', $pipeline['name']);
        $this->assertSame(
            [['New', 10, 'open'], ['Qualified', 25, 'open'], ['Proposal', 50, 'open'], ['Negotiation', 75, 'open'], ['Won', 100, 'won'], ['Lost', 0, 'lost']],
            array_map(fn (array $stage) => [$stage['name'], $stage['probability'], $stage['category']], $pipeline['stages']),
        );

        // Read again, none is made twice.
        $this->pipeline();
        $this->assertSame([1, 6], [CrmPipeline::query()->where('workspace_id', $this->business->id)->count(), CrmStage::query()->count()]);
    }

    public function test_the_pipeline_is_saved_whole_and_its_stages_keep_their_ids(): void
    {
        $this->crmOn();

        $pipeline = $this->pipeline();
        [$new, $qualified, $proposal, , $won, $lost] = $pipeline['stages'];

        // Renamed, a stage added and one left out, reordered; won and lost keep 100% and 0% whatever is sent.
        $saved = $this->save($pipeline['id'], 'Trade Sales', [
            [...$new, 'name' => 'Enquiry', 'probability' => 15],
            $qualified,
            ['id' => null, 'name' => 'Demo', 'probability' => 60, 'category' => 'open', 'is_active' => true],
            [...$proposal, 'is_active' => false],
            [...$won, 'probability' => 40],
            [...$lost, 'probability' => 30],
        ])->assertOk()->assertJsonPath('message', 'Pipeline saved.')->json('data');

        $this->assertSame('Trade Sales', $saved['name']);
        $this->assertSame(
            [[$new['id'], 'Enquiry', 15, true], [$qualified['id'], 'Qualified', 25, true], [null, 'Demo', 60, true], [$proposal['id'], 'Proposal', 50, false], [$won['id'], 'Won', 100, true], [$lost['id'], 'Lost', 0, true]],
            array_map(fn (array $stage) => [$stage['name'] === 'Demo' ? null : $stage['id'], $stage['name'], $stage['probability'], $stage['is_active']], $saved['stages']),
        );
        $this->assertSame(6, CrmStage::query()->count());
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the pipeline "Trade Sales"')->exists());
    }

    public function test_a_pipeline_keeps_an_open_a_won_and_a_lost_stage(): void
    {
        $this->crmOn();

        $pipeline = $this->pipeline();
        [$new, $qualified, , , $won, $lost] = $pipeline['stages'];

        $this->save($pipeline['id'], 'Sales Pipeline', [$new, $qualified, $lost])->assertUnprocessable()
            ->assertJsonValidationErrors(['stages' => 'A pipeline needs an open stage, a won stage and a lost stage, each switched on.']);
        $this->save($pipeline['id'], 'Sales Pipeline', [$new, [...$won, 'is_active' => false], $lost])->assertUnprocessable()->assertJsonValidationErrors('stages');
        $this->save($pipeline['id'], 'Sales Pipeline', [$new, [...$qualified, 'name' => 'new'], $won, $lost])->assertUnprocessable()
            ->assertJsonValidationErrors(['stages.1.name' => 'Each stage needs a name of its own.']);
        $this->save($pipeline['id'], 'Sales Pipeline', [[...$new, 'probability' => 120], $won, $lost])->assertUnprocessable()->assertJsonValidationErrors('stages.0.probability');
        $this->save($pipeline['id'], '', [$new, $won, $lost])->assertUnprocessable()->assertJsonValidationErrors('name');

        // Another business's pipeline is not found.
        $theirs = $this->businessOf(User::factory()->create())->crmPipelines()->create(['name' => 'Theirs']);
        $this->save($theirs->id, 'Mine', [$new, $won, $lost])->assertNotFound();
    }

    public function test_sales_teams_come_with_crm_and_have_a_manager(): void
    {
        // Sales Lite alone has none.
        $this->getJson('/api/v1/business-settings/sales-teams')->assertForbidden();

        $this->crmOn();

        $this->getJson('/api/v1/business-settings/sales-teams')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/v1/business-settings/sales-teams/people')->assertOk()->assertJsonPath('data', [['id' => $this->user->id, 'name' => $this->user->name]]);

        $team = $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'Dhaka North', 'member_ids' => [$this->user->id], 'manager_id' => $this->user->id])->assertCreated()
            ->assertJsonPath('data.manager', ['id' => $this->user->id, 'name' => $this->user->name])
            ->json('data.id');

        // Left out, the manager stays; someone outside the business is not one.
        $this->putJson("/api/v1/business-settings/sales-teams/{$team}", ['name' => 'Dhaka North', 'is_active' => false, 'member_ids' => []])->assertOk()
            ->assertJsonPath('data.manager.id', $this->user->id);
        $this->putJson("/api/v1/business-settings/sales-teams/{$team}", ['name' => 'Dhaka North', 'member_ids' => [], 'manager_id' => User::factory()->create()->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['manager_id' => 'Choose a member of this business who is active.']);
        $this->putJson("/api/v1/business-settings/sales-teams/{$team}", ['name' => 'Dhaka North', 'member_ids' => [], 'manager_id' => null])->assertOk()->assertJsonPath('data.manager', null);

        // CRM's settings permission changes them too.
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'CRM Admin', 'permissions' => ['settings.view']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'Chattogram', 'member_ids' => []])->assertForbidden();
        $role->forceFill(['permissions' => [...$role->permissions, 'crm.settings.manage']])->save();
        $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'Chattogram', 'member_ids' => [$member->id], 'manager_id' => $member->id])->assertCreated();
    }
}
