<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
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
 * Doc 17 Phase D2a: how an opportunity ends (sections 17.15 and 17.16) -
 * won at its final value, lost for a reason, reopened at an open stage with
 * the permission, and archived until it is restored.
 */
class CrmOpportunityOutcomesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $contact;

    /** @var array<string, int> the pipeline's stages by name */
    private array $stages;

    /** @var array<string, int> the lost reasons by name */
    private array $reasons;

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
        $this->stages  = collect($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages'))->pluck('id', 'name')->all();
        $this->reasons = collect($this->getJson('/api/v1/crm/opportunities/options')->json('data.lost_reasons'))->pluck('id', 'name')->all();
    }

    /** An open opportunity at $stage: its id. */
    private function opportunity(string $stage = 'Proposal'): int
    {
        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - shelving', 'contact_id' => $this->contact->id, 'stage_id' => $this->stages[$stage],
            'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20', 'competitors' => ['Bose Ltd'],
        ])->assertCreated()->json('data.id');
    }

    private function step(int $id, string $step, array $body = []): TestResponse
    {
        return $this->postJson("/api/v1/crm/opportunities/{$id}/{$step}", $body);
    }

    public function test_a_won_opportunity_goes_to_the_won_stage_at_its_final_value(): void
    {
        $id = $this->opportunity();

        $this->step($id, 'win', ['closed_on' => '2026-11-17', 'value' => '1800'])->assertJsonValidationErrors(['closed_on' => 'Give today or a day before.']);

        $this->step($id, 'win', ['closed_on' => '2026-11-15', 'value' => '1800', 'reason' => 'Best delivery time'])->assertOk()
            ->assertJsonPath('message', 'Marked won.')
            ->assertJsonPath('data.status', 'won')
            ->assertJsonPath('data.stage.name', 'Won')
            ->assertJsonPath('data.probability', 100)
            ->assertJsonPath('data.expected_value', '1800.00')
            ->assertJsonPath('data.weighted_value', '1800.00')
            ->assertJsonPath('data.outcome.on', '2026-11-15')
            ->assertJsonPath('data.outcome.by', $this->user->name)
            ->assertJsonPath('data.outcome.won_reason', 'Best delivery time')
            ->assertJsonPath('data.actions', ['reopen', 'archive'])
            ->assertJsonPath('data.visits.1.stage', 'Won');

        $this->assertTrue(ActivityLog::where('description', 'Marked opportunity "Uddin Traders - shelving" won')->exists());

        // Closed, it no longer changes, moves or ends again.
        $this->putJson("/api/v1/crm/opportunities/{$id}", ['name' => 'Renamed', 'contact_id' => $this->contact->id, 'expected_value' => '1', 'currency' => 'USD', 'expected_close_date' => '2026-12-20'])
            ->assertStatus(409)->assertJsonPath('message', 'A won or lost opportunity is reopened before it is changed.');
        $this->step($id, 'stage', ['stage_id' => $this->stages['New']])->assertStatus(409);
        $this->step($id, 'lose', ['closed_on' => '2026-11-16', 'lost_reason_id' => $this->reasons['Price']])->assertStatus(409)->assertJsonPath('message', 'Only an open opportunity is lost.');

        $this->assertSame(['won'], array_column($this->getJson('/api/v1/crm/opportunities?status=won')->json('data'), 'status'));
    }

    public function test_a_lost_opportunity_keeps_why_against_whom_and_when_to_follow_up(): void
    {
        $id = $this->opportunity();

        $this->step($id, 'lose', ['closed_on' => '2026-11-16'])->assertJsonValidationErrors(['lost_reason_id' => 'Choose why it was lost.']);
        $source = $this->getJson('/api/v1/crm/opportunities/options')->json('data.sources.0.id');
        $this->step($id, 'lose', ['closed_on' => '2026-11-16', 'lost_reason_id' => $source])->assertJsonValidationErrors(['lost_reason_id' => 'Choose one of the lost reasons in Settings → CRM.']);
        $this->step($id, 'lose', ['closed_on' => '2026-11-16', 'lost_reason_id' => $this->reasons['Price'], 'follow_up_on' => '2026-11-10'])
            ->assertJsonValidationErrors(['follow_up_on' => 'A follow-up is after the day it was lost.']);

        $this->step($id, 'lose', [
            'closed_on' => '2026-11-16', 'lost_reason_id' => $this->reasons['Chose Competitor'], 'competitor' => 'Bose Ltd', 'notes' => 'Ten percent cheaper.', 'follow_up_on' => '2027-03-01',
        ])->assertOk()
            ->assertJsonPath('message', 'Marked lost.')
            ->assertJsonPath('data.status', 'lost')
            ->assertJsonPath('data.stage.name', 'Lost')
            ->assertJsonPath('data.probability', 0)
            ->assertJsonPath('data.weighted_value', '0.00')
            ->assertJsonPath('data.outcome.lost_reason.name', 'Chose Competitor')
            ->assertJsonPath('data.outcome.competitor', 'Bose Ltd')
            ->assertJsonPath('data.outcome.notes', 'Ten percent cheaper.')
            ->assertJsonPath('data.outcome.follow_up_on', '2027-03-01');

        $this->assertTrue(ActivityLog::where('description', 'Marked opportunity "Uddin Traders - shelving" lost: Chose Competitor')->exists());

        // A reason an opportunity was lost for stays in Settings → CRM.
        $reason = $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'lost_reason', 'name' => 'Moved abroad'])->assertCreated()->json('data.id');
        $other  = $this->opportunity();
        $this->step($other, 'lose', ['closed_on' => '2026-11-16', 'lost_reason_id' => $reason])->assertOk();
        $this->deleteJson("/api/v1/business-settings/crm/options/{$reason}")->assertStatus(409)
            ->assertJsonPath('message', 'Opportunities were lost for this reason, so it stays. Switch it off instead.');
    }

    public function test_reopening_needs_its_permission_and_goes_back_to_the_last_open_stage(): void
    {
        $id = $this->opportunity('Qualified');
        $this->step($id, 'stage', ['stage_id' => $this->stages['Proposal']])->assertOk();

        $seller = $this->member(['crm.opportunities.view', 'crm.opportunities.close', 'crm.records.all']);
        Sanctum::actingAs($seller);
        $this->step($id, 'lose', ['closed_on' => '2026-11-16', 'lost_reason_id' => $this->reasons['No Budget']])->assertOk()
            ->assertJsonPath('data.actions', []);
        $this->step($id, 'reopen')->assertForbidden();

        Sanctum::actingAs($this->user);
        $this->step($id, 'reopen')->assertOk()
            ->assertJsonPath('message', 'Reopened at Proposal.')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.probability', 50)
            ->assertJsonPath('data.outcome', null);
        $this->assertSame(['Qualified', 'Proposal', 'Lost', 'Proposal'], array_column($this->getJson("/api/v1/crm/opportunities/{$id}")->json('data.visits'), 'stage'));
        $this->assertTrue(ActivityLog::where('description', 'Reopened opportunity "Uddin Traders - shelving" at Proposal')->exists());

        // Won, it can be reopened at another open stage.
        $this->step($id, 'win', ['closed_on' => '2026-11-16'])->assertOk()->assertJsonPath('data.expected_value', '2000.00');
        $this->step($id, 'reopen', ['stage_id' => $this->stages['Won']])->assertJsonValidationErrors(['stage_id']);
        $this->step($id, 'reopen', ['stage_id' => $this->stages['Negotiation']])->assertOk()->assertJsonPath('data.probability', 75);
    }

    public function test_an_archived_opportunity_leaves_the_lists_until_it_is_restored(): void
    {
        $id = $this->opportunity();

        $this->step($id, 'archive')->assertOk()
            ->assertJsonPath('message', 'Opportunity archived.')
            ->assertJsonPath('data.archived.by', $this->user->name)
            ->assertJsonPath('data.actions', ['restore']);

        $listed = fn (string $status) => array_column($this->getJson("/api/v1/crm/opportunities?status={$status}")->json('data'), 'id');
        $this->assertSame([], $listed(''));
        $this->assertSame([], $listed('all'));
        $this->assertSame([$id], $listed('archived'));

        $this->step($id, 'win', ['closed_on' => '2026-11-16'])->assertStatus(409)->assertJsonPath('message', 'An archived opportunity is restored before it changes.');
        $this->step($id, 'archive')->assertStatus(409)->assertJsonPath('message', 'This opportunity is archived already.');

        $this->step($id, 'restore')->assertOk()->assertJsonPath('message', 'Opportunity restored.')->assertJsonPath('data.archived', null);
        $this->assertSame([$id], $listed(''));
        $this->assertTrue(ActivityLog::where('description', 'Archived opportunity "Uddin Traders - shelving"')->exists());
        $this->assertTrue(ActivityLog::where('description', 'Restored opportunity "Uddin Traders - shelving"')->exists());
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id]);

        return $user;
    }
}
