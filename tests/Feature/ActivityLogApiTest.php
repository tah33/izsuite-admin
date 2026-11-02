<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Settings > Security & Audit > Activity Log (Doc 11 section 24) - Doc 11
 * Phase E: an entry belongs to the business it is about, an update records
 * each setting's previous and new value - sensitive ones masked - and a
 * business sees only its own entries, filtered and a page at a time.
 */
class ActivityLogApiTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const REGIONAL = ['date_format' => 'm/d/Y', 'time_format' => '12h', 'number_format' => 'comma_dot', 'week_start' => 'sunday', 'paper_size' => 'letter', 'fiscal_year_start_month' => 1];

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
    }

    private function log(array $query = [], array $headers = []): array
    {
        return $this->getJson('/api/v1/activity-log?'.http_build_query($query), $headers)->assertOk()->json();
    }

    public function test_an_entry_belongs_to_the_business_it_is_about(): void
    {
        $other   = $this->user->workspaces()->create(['name' => 'Second Business']);
        $picking = ['X-Workspace-Id' => (string) $other->id];

        $this->putJson('/api/v1/business-settings/regional', self::REGIONAL)->assertOk();
        $this->postJson('/api/v1/payment-terms', ['name' => '10 Days', 'days' => 10])->assertCreated();
        $this->postJson('/api/v1/payment-terms', ['name' => '20 Days', 'days' => 20], $picking)->assertCreated();

        $this->assertSame([$this->business->id, $this->business->id, $other->id], ActivityLog::orderBy('id')->pluck('workspace_id')->all());

        $this->assertSame(['Added payment term "10 Days"', 'Updated the regional settings'], array_column($this->log()['data'], 'description'));
        $this->assertSame(['Added payment term "20 Days"'], array_column($this->log([], $picking)['data'], 'description'));
    }

    public function test_an_update_records_each_setting_previous_and_new_value(): void
    {
        $this->putJson('/api/v1/business-settings/regional', self::REGIONAL)->assertOk();

        $entry = $this->log()['data'][0];

        $this->assertSame(['id', 'at', 'user', 'description', 'changes', 'location'], array_keys($entry));
        $this->assertSame($this->user->name, $entry['user']);
        $this->assertSame('2026-10-28T10:00:00+00:00', $entry['at']);
        $this->assertContains(['setting' => 'date_format', 'from' => 'd M Y', 'to' => 'm/d/Y'], $entry['changes']);
        $this->assertContains(['setting' => 'week_start', 'from' => 'monday', 'to' => 'sunday'], $entry['changes']);
        $this->assertNotContains('number_format', array_column($entry['changes'], 'setting'));

        // Settings saved apart from the business still say what changed.
        $this->putJson('/api/v1/business-settings/sales', [
            'payment_terms' => 30, 'quotation_validity' => 30, 'proforma_validity' => 15, 'line_discounts' => true, 'document_discounts' => false, 'notes' => null, 'terms' => null,
        ])->assertOk();

        $this->assertSame([
            ['setting' => 'payment_terms', 'from' => '0', 'to' => '30'],
            ['setting' => 'proforma_validity', 'from' => '30', 'to' => '15'],
            ['setting' => 'document_discounts', 'from' => true, 'to' => false],
        ], $this->log()['data'][0]['changes']);
    }

    public function test_sensitive_values_are_masked_and_a_change_behind_the_mask_still_shows(): void
    {
        $this->assertSame([
            ['setting' => 'account_number', 'from' => '••••3344', 'to' => '••••7766'],
            ['setting' => 'name', 'from' => 'City Bank', 'to' => 'City Bank Current'],
        ], SettingChanges::between(['account_number' => '0011223344', 'name' => 'City Bank'], ['account_number' => '0099887766', 'name' => 'City Bank Current']));

        $this->assertSame(
            [['setting' => 'swift_code', 'from' => '••••5678', 'to' => '••••5678']],
            SettingChanges::between(['swift_code' => 'AAAA5678'], ['swift_code' => 'BBBB5678']),
        );
        $this->assertSame([], SettingChanges::between(['notes' => null, 'updated_at' => '2026-10-01'], ['notes' => '', 'updated_at' => '2026-10-28']));
    }

    public function test_an_entry_about_a_record_at_a_location_names_the_location(): void
    {
        $location = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka Shop', 'code' => 'DHK', 'timezone' => 'Asia/Dhaka']);
        $account  = (new PaymentAccount)->forceFill(['id' => 99, 'workspace_id' => $this->business->id, 'location_id' => $location->id]);

        ActivityLogService::record('updated', 'Updated payment account "Till"', $account);

        $this->assertSame(['location_id' => $location->id], ActivityLog::sole()->properties);
        $this->assertSame('Dhaka Shop', $this->log()['data'][0]['location']);
    }

    public function test_the_log_is_searched_filtered_and_paged(): void
    {
        $staff = User::factory()->create(['first_name' => 'Rafi', 'last_name' => 'Ahmed']);

        $this->postJson('/api/v1/payment-terms', ['name' => '10 Days', 'days' => 10])->assertCreated();
        $this->travelTo(Carbon::parse('2026-10-29 10:00:00', 'UTC'));
        $this->putJson('/api/v1/business-settings/regional', self::REGIONAL)->assertOk();
        ActivityLog::create(['user_id' => $staff->id, 'workspace_id' => $this->business->id, 'action' => 'exported', 'description' => 'Exported contacts']);

        $this->assertSame(['Exported contacts', 'Updated the regional settings', 'Added payment term "10 Days"'], array_column($this->log()['data'], 'description'));
        $this->assertSame(['Added payment term "10 Days"'], array_column($this->log(['search' => 'term'])['data'], 'description'));
        $this->assertSame(['Updated the regional settings'], array_column($this->log(['changes_only' => 1])['data'], 'description'));
        $this->assertSame(['Exported contacts'], array_column($this->log(['user_id' => $staff->id])['data'], 'description'));
        $this->assertSame(['Added payment term "10 Days"'], array_column($this->log(['date_from' => '2026-10-28', 'date_to' => '2026-10-28'])['data'], 'description'));

        $this->assertSame(
            ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 3],
            array_intersect_key($this->log(['per_page' => 10])['pagination'], array_flip(['current_page', 'last_page', 'per_page', 'total'])),
        );

        $this->getJson('/api/v1/activity-log?date_from=2026-10-29&date_to=2026-10-28')
            ->assertJsonValidationErrors(['date_to' => 'The end date comes on or after the start date.']);

        $this->assertEqualsCanonicalizing(
            [['id' => $this->user->id, 'name' => $this->user->name], ['id' => $staff->id, 'name' => 'Rafi Ahmed']],
            $this->getJson('/api/v1/activity-log/users')->assertOk()->json('data'),
        );
    }

    public function test_entries_written_before_get_the_business_they_are_about(): void
    {
        $term      = $this->business->paymentTerms()->create(['name' => '30 Days', 'days' => 30]);
        $migration = require database_path('migrations/2025_01_01_000103_add_workspace_to_activity_logs.php');

        ActivityLog::query()->delete();
        $migration->down();

        $now = now();

        DB::table('activity_logs')->insert([
            ['user_id' => $this->user->id, 'action' => 'updated', 'model_type' => Workspace::class, 'model_id' => $this->business->id, 'description' => 'Updated the regional settings', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => $this->user->id, 'action' => 'created', 'model_type' => $term::class, 'model_id' => $term->id, 'description' => 'Added payment term "30 Days"', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => $this->user->id, 'action' => 'login', 'model_type' => null, 'model_id' => null, 'description' => 'Signed in', 'created_at' => $now, 'updated_at' => $now],
            // About a business deleted since: it stays the platform's.
            ['user_id' => $this->user->id, 'action' => 'deleted', 'model_type' => Workspace::class, 'model_id' => $this->business->id + 100, 'description' => 'Deleted workspace "Gone"', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $migration->up();

        $this->assertSame([$this->business->id, $this->business->id, null, null], ActivityLog::orderBy('id')->pluck('workspace_id')->all());
    }
}
