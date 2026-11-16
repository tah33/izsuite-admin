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
 * Doc 17 Phase D3a: a stage's stale-after period and the details it needs
 * before an opportunity enters it (section 17.13) - set in Settings → CRM,
 * checked on adding, moving, winning, losing, reopening and converting, and
 * stale opportunities marked and filtered.
 */
class CrmStageRulesTest extends TestCase
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

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->contact = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $this->stages  = collect($this->pipeline()['stages'])->pluck('id', 'name')->all();
    }

    private function pipeline(): array
    {
        return $this->getJson('/api/v1/business-settings/crm')->assertOk()->json('data.pipelines.0');
    }

    /** Saves the pipeline with $changes to its stages by name: [name => fields]. */
    private function setStages(array $changes): TestResponse
    {
        $pipeline = $this->pipeline();
        $stages   = array_map(fn (array $stage) => [...$stage, ...($changes[$stage['name']] ?? [])], $pipeline['stages']);

        return $this->putJson("/api/v1/business-settings/crm/pipelines/{$pipeline['id']}", ['name' => $pipeline['name'], 'stages' => $stages]);
    }

    private function add(string $stage, array $fields = []): TestResponse
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - shelving', 'contact_id' => $this->contact->id, 'stage_id' => $this->stages[$stage],
            'expected_value' => '2000', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20', ...$fields,
        ]);
    }

    private function move(int $id, string $stage): TestResponse
    {
        return $this->postJson("/api/v1/crm/opportunities/{$id}/stage", ['stage_id' => $this->stages[$stage]]);
    }

    public function test_settings_keep_each_stages_stale_period_and_what_it_needs(): void
    {
        $this->setStages([
            'Qualified' => ['stale_after_days' => 14, 'required_fields' => ['next_action', 'contact_person', 'next_action']],
        ])->assertJsonValidationErrors(['stages.1.required_fields.2']);
        $this->setStages(['Qualified' => ['required_fields' => ['notes']]])->assertJsonValidationErrors(['stages.1.required_fields.0']);
        $this->setStages(['Qualified' => ['stale_after_days' => 400]])->assertJsonValidationErrors(['stages.1.stale_after_days']);

        $this->setStages([
            'Qualified' => ['stale_after_days' => 14, 'required_fields' => ['next_action', 'contact_person']],
            'Won'       => ['stale_after_days' => 30, 'required_fields' => ['interested_items']],
        ])->assertOk();

        $stages = collect($this->pipeline()['stages'])->keyBy('name');
        // In Settings → CRM's order of the details; a won stage never goes stale.
        $this->assertSame([14, ['contact_person', 'next_action']], [$stages['Qualified']['stale_after_days'], $stages['Qualified']['required_fields']]);
        $this->assertSame([null, ['interested_items']], [$stages['Won']['stale_after_days'], $stages['Won']['required_fields']]);
        $this->assertSame([null, []], [$stages['New']['stale_after_days'], $stages['New']['required_fields']]);
    }

    public function test_an_opportunity_enters_a_stage_only_with_what_it_needs(): void
    {
        $this->setStages([
            'Qualified' => ['required_fields' => ['next_action']],
            'Proposal'  => ['required_fields' => ['requirements', 'competitors', 'sales_team_id']],
            'Won'       => ['required_fields' => ['interested_items']],
        ])->assertOk();

        $this->add('Qualified')->assertJsonValidationErrors(['stage_id' => 'Qualified needs Next Action filled in first.']);
        $id = $this->add('Qualified', ['next_action' => 'Send a quotation'])->assertCreated()->json('data.id');

        $this->move($id, 'Proposal')->assertJsonValidationErrors(['stage_id' => 'Proposal needs Sales Team, Customer Requirements and Competitors filled in first.']);

        $team = SalesTeam::create(['workspace_id' => $this->business->id, 'name' => 'North']);
        $this->putJson("/api/v1/crm/opportunities/{$id}", [
            'name' => 'Uddin Traders - shelving', 'contact_id' => $this->contact->id, 'expected_value' => '2000', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20',
            'next_action' => 'Send a quotation', 'requirements' => 'Shelving for a new shop', 'competitors' => ['Bose Ltd'], 'sales_team_id' => $team->id,
        ])->assertOk();
        $this->move($id, 'Proposal')->assertOk();

        // Won needs its interested items; lost needs nothing more. Emptying a detail later moves it nowhere.
        $this->postJson("/api/v1/crm/opportunities/{$id}/win", ['closed_on' => '2026-11-16'])->assertJsonValidationErrors(['stage_id' => 'Won needs Interested Items filled in first.']);
        $reason = $this->getJson('/api/v1/crm/opportunities/options')->json('data.lost_reasons.0.id');
        $this->postJson("/api/v1/crm/opportunities/{$id}/lose", ['closed_on' => '2026-11-16', 'lost_reason_id' => $reason])->assertOk();

        // Reopening goes back to Proposal, which still needs its three - here, all there.
        $this->postJson("/api/v1/crm/opportunities/{$id}/reopen")->assertOk()->assertJsonPath('data.stage.name', 'Proposal');
        $this->setStages(['Negotiation' => ['required_fields' => ['priority']]])->assertOk();
        $this->move($id, 'Negotiation')->assertJsonValidationErrors(['stage_id' => 'Negotiation needs Priority filled in first.']);
    }

    public function test_a_lead_converts_into_a_stage_only_with_what_it_carries_over(): void
    {
        $this->setStages(['Qualified' => ['required_fields' => ['sales_team_id', 'next_action']]])->assertOk();

        $lead = $this->postJson('/api/v1/crm/leads', [
            'name' => 'Rahim Uddin', 'email' => 'rahim@uddin.example', 'customer_need' => 'Shelving', 'next_action' => 'Call back',
            'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id'),
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();

        $convert = fn (string $stage) => $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => $this->contact->id, 'opportunity' => [
            'name' => 'Rahim - shelving', 'stage_id' => $this->stages[$stage], 'expected_value' => '1500', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20',
        ]]);

        $convert('Qualified')->assertJsonValidationErrors(['opportunity.stage_id' => 'Qualified needs Sales Team filled in first.']);
        $convert('New')->assertOk();
    }

    public function test_an_open_opportunity_at_its_stage_too_long_is_stale(): void
    {
        $this->setStages(['Proposal' => ['stale_after_days' => 5]])->assertOk();

        $waiting = $this->add('Proposal')->assertCreated()->json('data.id');
        $fresh   = $this->add('New', ['name' => 'No period'])->assertCreated()->json('data.id');

        $this->travel(5)->days();
        $this->getJson("/api/v1/crm/opportunities/{$waiting}")->assertJsonPath('data.stale', false)->assertJsonPath('data.days_at_stage', 5);

        $this->travel(1)->hours();
        $this->getJson("/api/v1/crm/opportunities/{$waiting}")->assertJsonPath('data.stale', true)->assertJsonPath('data.stage.stale_after_days', 5);

        $rows = collect($this->getJson('/api/v1/crm/opportunities')->json('data'))->keyBy('id');
        $this->assertSame([true, 5, false], [$rows[$waiting]['stale'], $rows[$waiting]['days_at_stage'], $rows[$fresh]['stale']]);
        $this->assertSame([$waiting], array_column($this->getJson('/api/v1/crm/opportunities?status=stale')->json('data'), 'id'));

        // Moved on, or archived, it is not stale.
        $this->move($waiting, 'Negotiation')->assertOk()->assertJsonPath('data.stale', false);
        $this->assertSame([], $this->getJson('/api/v1/crm/opportunities?status=stale')->json('data'));
    }
}
