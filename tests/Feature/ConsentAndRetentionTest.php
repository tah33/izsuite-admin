<?php

namespace Tests\Feature;

use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\ConsentRecord;
use App\Models\Admin\Role;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Doc 15 Phase G3 (section 15.24): consent records - the terms accepted at
 * sign-up, izSuite's product news by e-mail given or withdrawn, each kept
 * once with where it came from - and Admin > Data Retention, which shows
 * what is kept and what removes it, with the days an export lasts.
 * Saturday 14 November 2026.
 */
class ConsentAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and a new account is not taken for an admin.
        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));
    }

    private function signUp(string $email, array $fields = []): User
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Amina', 'username' => strtok($email, '@'), 'email' => $email,
            'password'   => 'Secret#123', 'password_confirmation' => 'Secret#123', 'accept_terms' => true, ...$fields,
        ], ['User-Agent' => 'Firefox/140'])->assertCreated();

        return User::where('email', $email)->firstOrFail();
    }

    public function test_signing_up_records_the_terms_and_product_news_when_ticked(): void
    {
        $amina = $this->signUp('amina@example.com', ['marketing' => true]);

        $this->assertTrue($amina->marketing_consent);
        $this->assertSame(
            [['marketing', true, 'registration'], ['terms', true, 'registration']],
            ConsentRecord::where('user_id', $amina->id)->orderByDesc('id')->get()->map(fn ($record) => [$record->kind, $record->given, $record->source])->all(),
        );
        $this->assertSame(['amina@example.com', '127.0.0.1', 'Firefox/140'], [ConsentRecord::first()->email, ConsentRecord::first()->ip_address, ConsentRecord::first()->user_agent]);

        // Not ticked: off, and only the terms are recorded.
        $karim = $this->signUp('karim@example.com');
        $this->assertFalse($karim->marketing_consent);
        $this->assertSame(['terms'], ConsentRecord::where('user_id', $karim->id)->pluck('kind')->all());
    }

    public function test_the_profile_gives_and_withdraws_it_each_change_recorded(): void
    {
        $user = $this->signUp('amina@example.com');
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['first_name' => 'Amina', 'marketing' => true])->assertOk()->assertJsonPath('data.marketing', true);
        $this->putJson('/api/v1/profile', ['first_name' => 'Amina', 'marketing' => true])->assertOk();

        $admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $this->actingAs($admin)->get(route('admin.clients.show', $user->id))->assertSee('Product news by e-mail: given.');
        Sanctum::actingAs($user);


        $this->putJson('/api/v1/profile', ['first_name' => 'Amina', 'marketing' => false])->assertOk()->assertJsonPath('data.marketing', false);
        $this->putJson('/api/v1/profile', ['first_name' => 'Amina Rahman'])->assertOk()->assertJsonPath('data.marketing', false);

        // Given once, withdrawn once: an unchanged choice is no new record.
        $this->assertSame([[false, 'profile'], [true, 'profile']], ConsentRecord::where('kind', 'marketing')->orderByDesc('id')->get()->map(fn ($record) => [$record->given, $record->source])->all());

        // The admin sees them on the account's page.
        $this->actingAs($admin)->get(route('admin.clients.show', $user->id))->assertOk()
            ->assertSee('Consent Records')->assertSee('Product news by e-mail: not given.')->assertSeeInOrder(['Withdrawn', 'Given', 'Given', 'Terms and conditions']);
    }

    public function test_data_retention_shows_the_rules_and_sets_the_days_an_export_lasts(): void
    {
        $admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);

        $this->actingAs($admin)->get(route('admin.retention.index'))->assertOk()
            ->assertSee('Privacy request exports')->assertSee('7 days after it is made')->assertSee('Enforced')->assertSee('Not yet');

        $this->putJson(route('admin.retention.update'), ['privacy_export_days' => 120])->assertJsonValidationErrors(['privacy_export_days']);
        $this->putJson(route('admin.retention.update'), ['privacy_export_days' => 14])->assertOk();

        $this->assertSame(14, app(\App\Services\Admin\Privacy\PrivacyRequestService::class)->exportDays());
        $this->get(route('admin.retention.index'))->assertSee('14 days after it is made');
        $this->assertSame(1, AdminAuditLog::where('description', 'Set privacy request exports to last 14 days')->count());

        // Read-only administrators see the rules; Support Administrators do not.
        $readOnly = User::factory()->create(['role_id' => Role::where('slug', 'read-only-administrator')->value('id')]);
        $support  = User::factory()->create(['role_id' => Role::where('slug', 'support-administrator')->value('id')]);
        $this->actingAs($readOnly)->get(route('admin.retention.index'))->assertOk()->assertDontSee('Their link works for');
        $this->actingAs($support)->get(route('admin.retention.index'))->assertForbidden();
    }
}
