<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Shared\ActivityLog;
use App\Models\User\Ticket;
use App\Models\User\User;
use App\Services\Shared\SupportSessionService;
use App\Support\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Multi-factor authentication (Doc 12 section 22) - Doc 12 Phase E2: a user
 * turns on an authenticator app with their password and the app's first
 * code; from then on, signing in asks for a code - or a recovery code, each
 * good once - after the password. Turning it off and new recovery codes need
 * the password; Login as from the admin panel does not ask.
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    // RFC 6238's test key, "12345678901234567890", in base32.
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-08 10:00:10', 'UTC'));

        // More sign-ins than a minute's limit allows; the limits are not what is tested here.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** Turns it on for $user through the API: the app's secret, and the recovery codes shown. */
    private function turnOn(User $user): array
    {
        Sanctum::actingAs($user);

        $secret = $this->postJson('/api/v1/profile/two-factor/setup', ['password' => 'password'])->assertOk()->json('data.secret');
        $codes  = $this->postJson('/api/v1/profile/two-factor/confirm', ['code' => Totp::code($secret, Totp::step())])->assertOk()->json('data.recovery_codes');

        $this->app['auth']->forgetGuards();

        return [$secret, $codes];
    }

    /** Signs in with $user's password: the challenge the app's code answers. */
    private function challengeFor(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('action', 'two_factor_required')
            ->assertJsonMissingPath('token')
            ->json('challenge');
    }

    public function test_codes_are_those_of_rfc_6238(): void
    {
        foreach ([59 => '287082', 1111111109 => '081804', 1234567890 => '005924', 2000000000 => '279037'] as $time => $code) {
            $this->assertSame($code, Totp::code(self::RFC_SECRET, Totp::step($time)));
        }

        // A phone a step behind or ahead still signs in; two steps off does not.
        $this->assertSame(Totp::step(1234567890) - 1, Totp::match(self::RFC_SECRET, Totp::code(self::RFC_SECRET, Totp::step(1234567890) - 1), 1234567890));
        $this->assertNull(Totp::match(self::RFC_SECRET, Totp::code(self::RFC_SECRET, Totp::step(1234567890) + 2), 1234567890));
        $this->assertNull(Totp::match(self::RFC_SECRET, 'abcdef', 1234567890));

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', Totp::secret());
        $this->assertStringStartsWith('otpauth://totp/izSuite:a%40b.test?secret='.self::RFC_SECRET.'&issuer=izSuite', Totp::uri(self::RFC_SECRET, 'a@b.test', 'izSuite'));
    }

    public function test_turning_it_on_takes_the_password_and_a_code_from_the_app(): void
    {
        $user  = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/two-factor/setup', ['password' => 'wrong'])->assertJsonValidationErrors('password');

        $setup = $this->postJson('/api/v1/profile/two-factor/setup', ['password' => 'password'])->assertOk()->json('data');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $setup['secret']);
        $this->assertStringStartsWith('otpauth://totp/izSuite:', $setup['uri']);
        $this->getJson('/api/v1/profile')->assertJsonPath('data.two_factor', false)->assertJsonPath('data.recovery_codes_left', null);

        $this->postJson('/api/v1/profile/two-factor/confirm', ['code' => 'abcdef'])->assertJsonValidationErrors('code');
        $codes = $this->postJson('/api/v1/profile/two-factor/confirm', ['code' => Totp::code($setup['secret'], Totp::step())])->assertOk()->json('data.recovery_codes');

        $this->assertCount(10, $codes);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $codes[0]);
        $this->getJson('/api/v1/profile')->assertJsonPath('data.two_factor', true)->assertJsonPath('data.recovery_codes_left', 10);
        $this->postJson('/api/v1/profile/two-factor/setup', ['password' => 'password'])->assertStatus(409);

        // The secret is kept encrypted, the recovery codes only as hashes.
        $row   = DB::table('users')->where('id', $user->id)->first();
        $this->assertStringNotContainsString($setup['secret'], $row->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], $row->two_factor_recovery_codes);
        $this->assertSame('Turned on two-factor authentication', ActivityLog::where('user_id', $user->id)->latest('id')->value('description'));
    }

    public function test_signing_in_asks_for_a_code_after_the_password(): void
    {
        $user             = User::factory()->create();
        [$secret, $codes] = $this->turnOn($user);

        $challenge        = $this->challengeFor($user);
        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => 'abcdef'])->assertJsonValidationErrors('code');

        // The code that turned it on was spent; the next one signs in, once.
        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => Totp::code($secret, Totp::step())])->assertJsonValidationErrors('code');
        $next             = Totp::code($secret, Totp::step() + 1);
        $token            = $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => $next])->assertOk()->assertJsonPath('user.email', $user->email)->json('token');
        $this->assertNotEmpty($token);
        $this->assertSame('code', ActivityLog::where('user_id', $user->id)->where('action', 'login')->latest('id')->first()->properties['two_factor']);
        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => $next])->assertJsonValidationErrors('challenge');
        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $this->challengeFor($user), 'code' => $next])->assertJsonValidationErrors('code');

        // A recovery code, however it is typed - once.
        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $this->challengeFor($user), 'recovery_code' => strtolower(str_replace('-', '', $codes[3]))])->assertOk();
        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $this->challengeFor($user), 'recovery_code' => $codes[3]])->assertJsonValidationErrors('recovery_code');
        $this->assertCount(9, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_a_challenge_ends_after_a_few_wrong_codes_or_five_minutes(): void
    {
        $user      = User::factory()->create();
        [$secret]  = $this->turnOn($user);

        $challenge = $this->challengeFor($user);

        foreach (range(1, 4) as $try) {
            $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => '000000'])->assertJsonValidationErrors('code');
        }

        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => '000000'])->assertJsonValidationErrors('challenge');
        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => Totp::code($secret, Totp::step() + 1)])->assertJsonValidationErrors('challenge');

        $late      = $this->challengeFor($user);
        $this->travel(6)->minutes();
        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $late, 'code' => Totp::code($secret, Totp::step())])->assertJsonValidationErrors('challenge');
    }

    public function test_new_recovery_codes_and_turning_it_off_need_the_password(): void
    {
        $user      = User::factory()->create();
        [, $codes] = $this->turnOn($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/two-factor/recovery-codes', ['password' => 'wrong'])->assertJsonValidationErrors('password');
        $fresh     = $this->postJson('/api/v1/profile/two-factor/recovery-codes', ['password' => 'password'])->assertOk()->json('data.recovery_codes');
        $this->assertCount(10, $fresh);
        $this->assertEmpty(array_intersect($codes, $fresh));

        $this->postJson('/api/v1/profile/two-factor/disable', ['password' => 'wrong'])->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/profile/two-factor/disable', ['password' => 'password'])->assertOk()->assertJsonPath('message', 'Two-factor authentication is off.');
        $this->getJson('/api/v1/profile')->assertJsonPath('data.two_factor', false);
        $this->postJson('/api/v1/profile/two-factor/recovery-codes', ['password' => 'password'])->assertStatus(409);

        // The password alone signs in again.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonMissingPath('action');
    }

    public function test_a_support_session_from_the_admin_panel_does_not_ask(): void
    {
        $user     = User::factory()->create();
        $this->turnOn($user);
        $admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $business = $user->workspaces()->create(['name' => 'Main Business']);
        $case     = Ticket::create(['workspace_id' => $business->id, 'user_id' => $user->id, 'subject' => 'Help with an invoice', 'status' => Ticket::OPEN]);

        $sessions = app(SupportSessionService::class);
        $session  = $sessions->open($business, $admin, ['ticket_id' => $case->id, 'reason' => 'Looking at the invoice', 'hours' => 1], edit: false);
        parse_str((string) parse_url($sessions->link($session, $admin), PHP_URL_QUERY), $query);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/support-session', ['code' => $query['code']])->assertOk()->assertJsonPath('user.two_factor', true);
    }

    public function test_secrets_never_reach_the_activity_log(): void
    {
        $user     = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/password/change', ['current_password' => 'password', 'password' => 'NewSecret!234', 'password_confirmation' => 'NewSecret!234'])->assertOk();
        $user->forceFill(['password' => 'password'])->save();
        [$secret] = $this->turnOn($user->fresh());

        $logged   = ActivityLog::where('user_id', $user->id)->pluck('properties')->map(fn ($properties) => json_encode($properties))->implode(' ');
        $this->assertStringNotContainsString('"password"', $logged);
        $this->assertStringNotContainsString('two_factor_secret', $logged);
        $this->assertStringNotContainsString($secret, $logged);

        // An entry from before keeps everything but its secrets.
        $before   = ActivityLog::create(['user_id' => $user->id, 'action' => 'updated', 'description' => 'Changed their password', 'properties' => [
            'source' => 'api', 'changes' => [['setting' => 'password', 'from' => 'old-hash', 'to' => 'new-hash'], ['setting' => 'phone', 'from' => null, 'to' => '123']],
        ]]);

        (require database_path('migrations/2025_01_01_000110_scrub_secrets_from_activity_log.php'))->up();

        $this->assertSame(['source' => 'api', 'changes' => [['setting' => 'phone', 'from' => null, 'to' => '123']]], $before->fresh()->properties);
    }
}
