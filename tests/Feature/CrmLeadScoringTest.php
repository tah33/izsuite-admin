<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\CrmLead;
use App\Models\User\CrmScoringRule;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase I2: lead scoring (section 17.22) - its rules kept in Settings
 * → CRM under Manage Assignment and Scoring Rules (17.30), and an open lead's
 * score: its fitting rules' points added up from 0 to 100, with the rules that
 * gave it, scored again as it changes, as work is done with it, every hour
 * and when the rules change. A closed lead keeps its score; with no rule
 * switched on, no lead has one. A score never qualifies or rejects a lead.
 */
class CrmLeadScoringTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const RULES = '/api/v1/business-settings/crm/scoring-rules';

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->save();
        $this->business->exchangeRates()->create(['currency' => 'EUR', 'base_currency' => 'USD', 'rate' => '1.08', 'effective_date' => '2026-10-01']);
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Main Business', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Road 1', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo',
        ]);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
    }

    /** A member whose role holds $permissions. */
    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    private function source(string $name): int
    {
        return collect($this->getJson('/api/v1/crm/leads/options')->json('data.sources'))->firstWhere('name', $name)['id'];
    }

    private function rule(string $name, string $factor, array $fields, int $points): int
    {
        return $this->postJson(self::RULES, ['name' => $name, 'factor' => $factor, ...$fields, 'points' => $points])->assertCreated()->json('data.id');
    }

    /** A lead from the website with a phone, unless $fields say otherwise: the lead as the API answers. */
    private function lead(string $name, array $fields = []): array
    {
        return $this->postJson('/api/v1/crm/leads', ['name' => $name, 'source_id' => $this->source('Website'), 'phone' => '+880 1711-000000', ...$fields])->assertCreated()->json('data');
    }

    /** $lead's form saved with $changes: what it is called, where it came from and how to reach it, as it was. */
    private function edit(array $lead, array $changes): \Illuminate\Testing\TestResponse
    {
        $now = $this->getJson("/api/v1/crm/leads/{$lead['id']}")->json('data');

        return $this->putJson("/api/v1/crm/leads/{$lead['id']}", [
            'name' => $now['name'], 'source_id' => $now['source']['id'], 'phone' => $now['phone'], 'email' => $now['email'], 'version' => $now['version'], ...$changes,
        ]);
    }

    private function score(int $lead): ?int
    {
        return $this->getJson("/api/v1/crm/leads/{$lead}")->assertOk()->json('data.score');
    }

    public function test_scoring_rules_are_kept_in_settings_with_their_own_permission(): void
    {
        $this->getJson(self::RULES)->assertOk()->assertExactJson(['data' => []]);

        $this->postJson(self::RULES, ['name' => 'Website leads', 'factor' => 'source', 'source_id' => $this->source('Website'), 'points' => 20])->assertCreated()
            ->assertJsonPath('message', 'Rule added. The open leads are being scored again.')
            ->assertJsonPath('data.factor', 'source')
            ->assertJsonPath('data.source.name', 'Website')
            ->assertJsonPath('data.points', 20)
            ->assertJsonPath('data.is_active', true);
        $email = $this->rule('Has an e-mail', 'detail', ['option' => 'email'], 10);
        $this->rule('Big value', 'value', ['threshold' => '1000'], 25);

        // Refused: what the factor needs left out or not one of its own, no points or too many, a count in parts, a name taken, what is not a lead source.
        $this->postJson(self::RULES, ['name' => 'No source', 'factor' => 'source', 'points' => 5])->assertUnprocessable()->assertJsonValidationErrors(['source_id' => 'Choose the lead source.']);
        $this->postJson(self::RULES, ['name' => 'Fax', 'factor' => 'detail', 'option' => 'fax', 'points' => 5])->assertUnprocessable()->assertJsonValidationErrors('option');
        $this->postJson(self::RULES, ['name' => 'Size', 'factor' => 'business_size', 'points' => 5])->assertUnprocessable()->assertJsonValidationErrors(['option' => 'Type the words its business size or type must have.']);
        $this->postJson(self::RULES, ['name' => 'Nothing', 'factor' => 'detail', 'option' => 'phone', 'points' => 0])->assertUnprocessable()->assertJsonValidationErrors(['points' => 'Give it points: more, or fewer with a minus.']);
        $this->postJson(self::RULES, ['name' => 'Too much', 'factor' => 'detail', 'option' => 'phone', 'points' => 150])->assertUnprocessable()->assertJsonValidationErrors(['points' => 'Points go from -100 to 100.']);
        $this->postJson(self::RULES, ['name' => 'Busy', 'factor' => 'engagement', 'threshold' => '2.5', 'points' => 5])->assertUnprocessable()->assertJsonValidationErrors(['threshold' => 'Enter a whole number.']);
        $this->postJson(self::RULES, ['name' => 'Has an e-mail', 'factor' => 'detail', 'option' => 'phone', 'points' => 5])->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Another scoring rule has this name.']);
        $call = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->firstWhere('key', 'call')['id'];
        $this->postJson(self::RULES, ['name' => 'Calls', 'factor' => 'source', 'source_id' => $call, 'points' => 5])->assertUnprocessable()->assertJsonValidationErrors(['source_id' => 'Choose one of your lead sources.']);

        // Changed and switched off: only what its factor asks is kept.
        $this->putJson(self::RULES."/{$email}", ['name' => 'Gone quiet', 'factor' => 'quiet', 'option' => 'email', 'source_id' => $this->source('Website'), 'threshold' => '7', 'points' => -10, 'is_active' => false])->assertOk()
            ->assertJsonPath('message', 'Rule saved. The open leads are being scored again.')
            ->assertJsonPath('data.factor', 'quiet')
            ->assertJsonPath('data.threshold', '7.00')
            ->assertJsonPath('data.option', null)
            ->assertJsonPath('data.source', null)
            ->assertJsonPath('data.points', -10)
            ->assertJsonPath('data.is_active', false);
        $this->assertSame(['Website leads', 'Gone quiet', 'Big value'], array_column($this->getJson(self::RULES)->json('data'), 'name'));

        $this->deleteJson(self::RULES."/{$email}")->assertOk()->assertJsonPath('message', 'Rule deleted. The open leads are being scored again.');
        $this->assertTrue(ActivityLog::query()->where('description', 'Added the scoring rule "Website leads"')->exists());
        $this->assertTrue(ActivityLog::query()->where('description', 'Deleted the scoring rule "Gone quiet"')->exists());
        $this->deleteJson(self::RULES."/{$email}")->assertNotFound();

        // Manage Assignment and Scoring Rules is its own permission.
        Sanctum::actingAs($this->member(['settings.view', 'crm.settings.manage']));
        $this->getJson(self::RULES)->assertForbidden();
        Sanctum::actingAs($this->member(['settings.view', 'crm.rules.manage']));
        $this->getJson(self::RULES)->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_lead_s_score_adds_up_its_rules_kept_from_0_to_100_with_what_made_it(): void
    {
        $racking = $this->postJson('/api/v1/items', ['type' => 'physical', 'usage' => 'both', 'name' => 'Racking', 'unit' => 'Piece'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/crm/leads/options')->assertJsonPath('data.scoring', false);
        $unscored = $this->lead('Before any rule');
        $this->assertSame([null, []], [$unscored['score'], $unscored['score_factors']]);

        $this->rule('Website leads', 'source', ['source_id' => $this->source('Website')], 20);
        $this->rule('Has an e-mail', 'detail', ['option' => 'email'], 10);
        $this->rule('Has a phone', 'detail', ['option' => 'phone'], 10);
        $this->rule('Big value', 'value', ['threshold' => '1000'], 25);
        $this->rule('Wants racking', 'item', ['item_id' => $racking], 15);
        $this->rule('Buys this month', 'purchase_period', ['option' => 'this_month'], 20);
        $this->rule('Wholesale', 'business_size', ['option' => 'wholesale'], 10);
        $quiet = $this->rule('Gone quiet', 'quiet', ['threshold' => '7'], -30);
        $this->getJson('/api/v1/crm/leads/options')->assertJsonPath('data.scoring', true);

        // The rules' change scored the lead already there: from the website, with a phone.
        $this->assertSame(30, $this->score($unscored['id']));

        // 1,000 euros is 1,080 dollars today: every rule but the quiet one, 110 points - kept at 100.
        $hot = $this->lead('Karim Ahmed', [
            'email' => 'karim@stores.test', 'phone' => '+880 1711-000000', 'estimated_value' => '1000', 'currency' => 'EUR',
            'interested_items' => [['item_id' => $racking, 'quantity' => 4]], 'purchase_period' => 'this_month', 'business_size' => 'Wholesale trader',
        ]);
        $this->assertSame(100, $hot['score']);
        $this->assertSame(['name' => 'Big value', 'points' => 25], $hot['score_factors'][0]);
        $this->assertEqualsCanonicalizing(['Big value', 'Website leads', 'Buys this month', 'Wants racking', 'Has an e-mail', 'Has a phone', 'Wholesale'], array_column($hot['score_factors'], 'name'));
        $this->assertSame(110, array_sum(array_column($hot['score_factors'], 'points')));

        // A referral worth 900 dollars, with a phone only: 10.
        $cold = $this->lead('Rahim Uddin', ['source_id' => $this->source('Referral'), 'phone' => '+880 1811-000000', 'estimated_value' => '900', 'currency' => 'USD']);
        $this->assertSame([10, [['name' => 'Has a phone', 'points' => 10]]], [$cold['score'], $cold['score_factors']]);

        // The Leads page shows each score and filters by band.
        $this->assertSame(['Karim Ahmed'], array_column($this->getJson('/api/v1/crm/leads?score=hot')->assertOk()->json('data'), 'name'));
        $this->assertEqualsCanonicalizing(['Rahim Uddin', 'Before any rule'], array_column($this->getJson('/api/v1/crm/leads?score=cold')->json('data'), 'name'));
        $this->assertSame([], $this->getJson('/api/v1/crm/leads?score=warm')->json('data'));
        $this->getJson('/api/v1/crm/leads?score=boiling')->assertUnprocessable()->assertJsonValidationErrors('score');
        $this->assertSame(100, collect($this->getJson('/api/v1/crm/leads')->json('data'))->firstWhere('name', 'Karim Ahmed')['score']);

        // Edited, it is scored again: an e-mail now.
        $this->edit($cold, ['email' => 'rahim@uddin.test'])->assertOk()->assertJsonPath('data.score', 20);

        // Eight days on, the hourly run finds both quiet - its score is not a change to the lead.
        $version = $this->getJson("/api/v1/crm/leads/{$hot['id']}")->json('data.version');
        $this->travel(8)->days();
        $this->artisan('crm:score-leads')->expectsOutput('Changed 3 lead scores.')->assertSuccessful();
        $this->assertSame([80, 0, 0], [$this->score($hot['id']), $this->score($cold['id']), $this->score($unscored['id'])]);
        $this->assertSame($version, $this->getJson("/api/v1/crm/leads/{$hot['id']}")->json('data.version'));
        $this->artisan('crm:score-leads')->expectsOutput('Changed 0 lead scores.')->assertSuccessful();

        // Marked contacted, it is not quiet; never qualified by its score.
        $this->postJson("/api/v1/crm/leads/{$hot['id']}/contact")->assertOk()->assertJsonPath('data.score', 100)->assertJsonPath('data.status', 'contacted');

        // A closed lead keeps the score it had; a change to the rules - one switched off - scores the open ones again.
        $this->postJson("/api/v1/crm/leads/{$cold['id']}/archive", ['reason' => 'Not now'])->assertOk()->assertJsonPath('data.score', 0);
        CrmScoringRule::query()->whereKey($quiet)->delete();
        $this->putJson(self::RULES.'/'.CrmScoringRule::query()->where('name', 'Wants racking')->value('id'), ['name' => 'Wants racking', 'factor' => 'item', 'item_id' => $racking, 'points' => 15, 'is_active' => false])->assertOk();
        $this->assertSame([95, 0, 30], [$this->score($hot['id']), $this->score($cold['id']), $this->score($unscored['id'])]);

        // Reopened, it is scored again: an e-mail and a phone.
        $this->postJson("/api/v1/crm/leads/{$cold['id']}/reopen")->assertOk()->assertJsonPath('data.score', 20);

        // With no rule switched on, no lead has a score - closed or open.
        $this->postJson("/api/v1/crm/leads/{$unscored['id']}/archive")->assertOk();
        foreach (CrmScoringRule::query()->pluck('id') as $id) {
            $this->deleteJson(self::RULES."/{$id}")->assertOk();
        }
        $this->assertSame([null, null, null], [$this->score($hot['id']), $this->score($cold['id']), $this->score($unscored['id'])]);
        $this->getJson('/api/v1/crm/leads/options')->assertJsonPath('data.scoring', false);
        $this->assertSame([], $this->getJson("/api/v1/crm/leads/{$hot['id']}")->json('data.score_factors'));
    }

    public function test_work_done_with_a_lead_its_response_its_details_and_location_count(): void
    {
        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK'])->id;
        $ctg   = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG'])->id;
        $types = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->pluck('id', 'key')->all();

        $this->rule('At Chattogram', 'location', ['location_id' => $ctg], 10);
        $this->rule('Contacted', 'response', ['option' => 'contacted'], 20);
        $this->rule('Waiting too long', 'response', ['option' => 'late'], -10);
        $this->rule('Followed up', 'engagement', ['threshold' => '2'], 30);
        $this->rule('Knows who decides', 'detail', ['option' => 'decision_maker'], 15);
        $this->rule('Has interests', 'detail', ['option' => 'interested_items'], 5);

        $lead  = $this->lead('Karim Ahmed', ['location_id' => $ctg, 'email' => 'karim@stores.test']);
        $other = $this->lead('Rahim Uddin', ['location_id' => $dhaka]);
        $this->assertSame([10, 0], [$lead['score'], $other['score']]);

        // Three days on, neither was contacted within Settings → CRM's two days.
        $this->travel(3)->days();
        $this->artisan('crm:score-leads')->assertSuccessful();
        $this->assertSame([0, 0], [$this->score($lead['id']), $this->score($other['id'])]);

        // An activity done answers it, and counts as work done; an e-mail sent counts too.
        $activity = $this->postJson('/api/v1/crm/activities', ['lead_id' => $lead['id'], 'type_id' => $types['task'], 'subject' => 'Send the brochure', 'due_on' => '2026-11-21', 'due_time' => '10:00'])->assertCreated()->json('data.id');
        $this->assertSame(0, $this->score($lead['id']));
        $this->postJson("/api/v1/crm/activities/{$activity}/complete")->assertOk();
        $this->assertSame(30, $this->score($lead['id']));
        $this->postJson('/api/v1/crm/emails', ['lead_id' => $lead['id'], 'to' => 'karim@stores.test', 'subject' => 'Our racking', 'body' => 'Dear Karim, here it is.'])->assertCreated();
        $this->assertSame(60, $this->score($lead['id']));

        // Who decides and what it wants - an item typed in counts.
        $this->edit($lead, ['decision_maker' => 'Mr Karim', 'interested_items' => [['name' => 'Cold-room shelving', 'quantity' => 2]]])->assertOk()
            ->assertJsonPath('data.score', 80);
        $this->assertSame(['Followed up', 'Contacted', 'Knows who decides', 'At Chattogram', 'Has interests'], array_column($this->getJson("/api/v1/crm/leads/{$lead['id']}")->json('data.score_factors'), 'name'));

        // Marked contacted, the other is not late any more.
        $this->postJson("/api/v1/crm/leads/{$other['id']}/contact")->assertOk()->assertJsonPath('data.score', 20);

        // A business without CRM in use is passed over by the hourly run: its new lead is not found late.
        $late = $this->lead('Late one', ['location_id' => $ctg]);
        $this->assertSame(10, $late['score']);
        $this->postJson('/api/v1/apps/crm/deactivate')->assertOk();
        $this->travel(3)->days();
        $this->artisan('crm:score-leads')->expectsOutput('Changed 0 lead scores.')->assertSuccessful();
        $this->assertSame(10, CrmLead::query()->whereKey($late['id'])->value('score'));
    }
}
