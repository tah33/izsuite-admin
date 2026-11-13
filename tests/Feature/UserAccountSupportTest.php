<?php

namespace Tests\Feature;

use App\Mail\AccountNoticeMail;
use App\Mail\PasswordChanged;
use App\Mail\VerifyEmailOtp;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase B2: support's actions on a user's account (section 15.7) -
 * resending verification, a single-use reset link, signing out everywhere,
 * locking, and resetting two-factor sign-in - and the list's checks and
 * security alerts. Nobody sees or sets a password.
 */
class UserAccountSupportTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-13 10:00:00', 'UTC'));
        Mail::fake();

        $this->admin  = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
        $this->client = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim', 'email' => 'nadia@karim.test', 'phone' => '+8801711000000']);
    }

    /** An admin's request: as $admin, or the seeded Super Admin. */
    private function asAdmin(?User $admin = null): static
    {
        return $this->actingAs($admin ?? $this->admin);
    }

    /** A request from the website, signed in as nobody. */
    private function asVisitor(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function signInAs(User $user, string $password = 'password')
    {
        return $this->asVisitor()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password]);
    }

    private function twoFactorOn(User $user): void
    {
        $user->forceFill(['two_factor_secret' => Totp::secret(), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => []])->save();
    }

    public function test_the_list_shows_each_accounts_checks_and_filters_by_them(): void
    {
        $this->twoFactorOn($this->client);
        User::factory()->unverified()->create(['first_name' => 'Selim', 'last_name' => 'Uddin', 'phone' => '+8801711000000']);
        User::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Mia', 'status' => User::LOCKED]);

        // Newest first; each row's words come after its name (the filters above say some of them too).
        $this->asAdmin()->get(route('admin.clients.index'))->assertOk()
            ->assertSeeInOrder(['Rahim Mia', 'Locked', 'Selim Uddin', 'Email not verified', 'Shared phone number', 'Nadia Karim', 'Two-factor on', 'Shared phone number']);

        $this->get(route('admin.clients.index', ['status' => 'locked']))->assertSee('Rahim Mia')->assertDontSee('Nadia Karim');
        $this->get(route('admin.clients.index', ['verified' => 'no']))->assertSee('Selim Uddin')->assertDontSee('Nadia Karim');
        $this->get(route('admin.clients.index', ['two_factor' => 'on']))->assertSee('Nadia Karim')->assertDontSee('Selim Uddin');
        $this->get(route('admin.clients.index', ['alerts' => 'yes']))->assertSee('Nadia Karim')->assertSee('Selim Uddin')->assertDontSee('Rahim Mia');
        $this->get(route('admin.clients.index', ['search' => '+880171']))->assertSee('Selim Uddin')->assertDontSee('Rahim Mia');
    }

    public function test_failed_sign_ins_are_kept_against_the_account_and_make_an_alert(): void
    {
        foreach (range(1, 5) as $try) {
            $this->signInAs($this->client, 'wrong-password')->assertUnauthorized();
        }
        $this->asVisitor()->postJson('/api/v1/auth/login', ['email' => 'nobody@nowhere.test', 'password' => 'x'])->assertUnauthorized();

        $this->assertSame(5, ActivityLog::where('action', 'login_failed')->where('user_id', $this->client->id)->count());
        $this->assertSame(5, ActivityLog::where('action', 'login_failed')->count());

        $this->asAdmin()->get(route('admin.clients.index', ['alerts' => 'yes']))->assertSee('5 failed sign-ins');
        $this->get(route('admin.clients.show', $this->client->id))->assertOk()->assertSee('A sign-in failed: wrong password');

        // A week on, the alert has passed.
        $this->travel(8)->days();
        $this->get(route('admin.clients.index', ['alerts' => 'yes']))->assertDontSee('Nadia Karim');
    }

    public function test_a_reset_link_works_once_for_an_hour_and_nobody_else_sees_it(): void
    {
        $this->asAdmin()->postJson(route('admin.clients.recovery', $this->client->id), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason' => 'Give the reason. The audit log keeps it.']);

        $response = $this->postJson(route('admin.clients.recovery', $this->client->id), ['reason' => 'Asked by phone, cannot sign in'])->assertOk();
        $link     = $this->linkMailed();
        $this->assertStringNotContainsString($link['token'], $response->getContent());
        $this->assertSame('nadia@karim.test', $link['email']);
        $this->assertSame('Asked by phone, cannot sign in', AdminAuditLog::where('action', 'recovery_link_sent')->sole()->reason);

        $reset = fn (array $link) => $this->asVisitor()->postJson('/api/v1/auth/password/reset', [
            'email' => $link['email'], 'reset_token' => $link['token'], 'password' => 'A-new-pass-2026', 'password_confirmation' => 'A-new-pass-2026',
        ]);

        $reset($link)->assertOk();
        Mail::assertSent(PasswordChanged::class);
        $reset($link)->assertUnprocessable();
        $this->signInAs($this->client, 'A-new-pass-2026')->assertOk();

        // A second link, opened too late.
        $this->asAdmin()->postJson(route('admin.clients.recovery', $this->client->id), ['reason' => 'Asked again'])->assertOk();
        $late = $this->linkMailed();
        $this->travel(61)->minutes();
        $reset($late)->assertUnprocessable();
    }

    /** @return array{email: string, token: string} the newest reset link's address and ticket */
    private function linkMailed(): array
    {
        $url = null;
        Mail::assertQueued(AccountNoticeMail::class, function (AccountNoticeMail $mail) use (&$url) {
            if ($mail->title === 'Choose a new password' && $mail->hasTo('nadia@karim.test')) {
                $url = $mail->actionUrl;
            }

            return true;
        });
        $this->assertStringContainsString('/reset-password?', (string) $url);
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        return ['email' => $query['email'], 'token' => $query['token']];
    }

    public function test_signing_out_everywhere_ends_every_session(): void
    {
        $this->client->createToken('phone');
        $this->client->createToken('laptop');

        $this->asAdmin()->postJson(route('admin.clients.sign-out', $this->client->id), ['reason' => 'Lost their laptop'])
            ->assertOk()->assertJsonPath('message', '2 sessions ended.');

        $this->assertSame(0, $this->client->tokens()->count());
        $this->assertSame(2, AdminAuditLog::where('action', 'signed_out')->sole()->properties['sessions']);
    }

    public function test_a_locked_account_cannot_sign_in_until_it_is_unlocked(): void
    {
        $this->client->createToken('phone');

        $this->asAdmin()->postJson(route('admin.clients.lock', $this->client->id), ['reason' => 'Payments look stolen'])->assertOk();

        $this->assertSame(User::LOCKED, $this->client->fresh()->status);
        $this->assertSame(0, $this->client->tokens()->count());
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->title === 'Your account is locked' && ! str_contains(implode(' ', $mail->lines), 'stolen'));
        $this->signInAs($this->client)->assertForbidden()->assertJsonPath('message', 'This account is locked. Contact izSuite support to unlock it.');

        $this->asAdmin()->postJson(route('admin.clients.lock', $this->client->id), ['reason' => 'Again'])->assertStatus(422);
        $this->postJson(route('admin.clients.unlock', $this->client->id), ['reason' => 'The owner proved the payments'])->assertOk();

        $this->assertSame(User::ACTIVE, $this->client->fresh()->status);
        $this->signInAs($this->client)->assertOk();
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->title === 'Your account is unlocked');
        $this->assertSame(['Payments look stolen', 'The owner proved the payments'], AdminAuditLog::whereIn('action', ['locked', 'unlocked'])->orderBy('id')->pluck('reason')->all());
    }

    public function test_two_factor_is_reset_only_after_the_check_the_password_and_a_reason(): void
    {
        $this->twoFactorOn($this->client);
        $this->client->createToken('phone');
        $url = route('admin.clients.two-factor', $this->client->id);

        $this->asAdmin()->postJson($url, ['confirm_password' => 'password', 'reason' => 'Lost their phone'])
            ->assertUnprocessable()->assertJsonValidationErrors(['identity_check' => 'Say how you checked that the person asking owns the account.']);
        $this->postJson($url, ['identity_check' => 'Wrote from the account address', 'confirm_password' => 'wrong', 'reason' => 'Lost their phone'])
            ->assertUnprocessable()->assertJsonValidationErrors(['confirm_password' => 'That password is not right.']);
        $this->assertNotNull($this->client->fresh()->two_factor_confirmed_at);

        $this->postJson($url, ['identity_check' => 'Wrote from the account address', 'confirm_password' => 'password', 'reason' => 'Lost their phone'])->assertOk();

        $client = $this->client->fresh();
        $this->assertNull($client->two_factor_confirmed_at);
        $this->assertNull($client->two_factor_secret);
        $this->assertSame(0, $client->tokens()->count());
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->title === 'Two-factor sign-in is off for your account');

        $entry = AdminAuditLog::where('action', 'two_factor_reset')->sole();
        $this->assertSame('Lost their phone', $entry->reason);
        $this->assertSame('Wrote from the account address', $entry->properties['identity_check']);

        $this->postJson($url, ['identity_check' => 'Again', 'confirm_password' => 'password', 'reason' => 'Again'])->assertStatus(422);
    }

    public function test_verification_is_resent_to_an_account_not_verified_yet(): void
    {
        $unverified = User::factory()->unverified()->create();

        $this->asAdmin()->postJson(route('admin.clients.verification', $unverified->id))->assertOk();
        Mail::assertSent(VerifyEmailOtp::class, fn (VerifyEmailOtp $mail) => $mail->hasTo($unverified->email));
        $this->assertSame('verification_sent', AdminAuditLog::latest('id')->first()->action);

        $this->postJson(route('admin.clients.verification', $this->client->id))->assertStatus(422);
    }

    public function test_the_page_shows_the_businesses_they_are_in_and_the_admin_activity(): void
    {
        $own = $this->businessOf($this->client);
        $own->update(['name' => 'Karim Stores']);
        app(SetUpNewWorkspace::class)->addRoles($own, $this->client, 'api');

        $other    = User::factory()->create();
        $theirs   = $this->businessOf($other);
        $theirs->update(['name' => 'Uddin Traders']);
        app(SetUpNewWorkspace::class)->addRoles($theirs, $other, 'api');
        $theirs->members()->create([
            'user_id' => $this->client->id, 'role_id' => $theirs->roles()->where('name', 'Sales User')->value('id'),
            'name'    => $this->client->name, 'email' => $this->client->email, 'status' => WorkspaceMember::ACTIVE, 'location_access' => WorkspaceMember::ALL_LOCATIONS,
        ]);

        $this->asAdmin()->postJson(route('admin.clients.sign-out', $this->client->id), ['reason' => 'Asked to'])->assertOk();

        $this->get(route('admin.clients.show', $this->client->id))->assertOk()
            ->assertSee('Businesses They Are In')->assertSee('Karim Stores')->assertSee('Uddin Traders')->assertSee('Sales User')
            ->assertSee('Signed Nadia Karim out everywhere');
        $this->get(route('admin.clients.index'))->assertSee('In 2 businesses');
    }

    public function test_each_role_does_only_the_account_actions_of_its_area(): void
    {
        $role    = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);
        $reason  = ['reason' => 'Checking the account'];

        $support = $role('support-administrator');
        $this->asAdmin($support)->postJson(route('admin.clients.recovery', $this->client->id), $reason)->assertOk();
        $this->postJson(route('admin.clients.lock', $this->client->id), $reason)->assertForbidden();

        $compliance = $role('compliance-and-security-administrator');
        $this->asAdmin($compliance)->postJson(route('admin.clients.lock', $this->client->id), $reason)->assertOk();
        $this->postJson(route('admin.clients.recovery', $this->client->id), $reason)->assertForbidden();
        $this->postJson(route('admin.clients.two-factor', $this->client->id), $reason)->assertForbidden();

        $this->asAdmin($role('read-only-administrator'))->postJson(route('admin.clients.sign-out', $this->client->id), $reason)->assertForbidden();
        $this->get(route('admin.clients.show', $this->client->id))->assertOk()->assertDontSee('Sign Out Everywhere');
    }
}
