<?php

namespace Tests\Feature;

use App\Mail\AdminNewSignIn;
use App\Models\Admin\AdminSignIn;
use App\Models\Admin\Role;
use App\Models\User\User;
use App\Services\Admin\AdminLoginService;
use App\Services\Admin\AdminSessionService;
use App\Services\Admin\AdminTwoFactorService;
use App\Services\Admin\RoleService;
use App\Services\Admin\StaffService;
use App\Support\RecoveryCodes;
use App\Support\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Doc 15 Phase A1: signing in to the admin panel (section 15.2). The three
 * faults the gap check found: a switched-off admin still signing in, roles
 * made in Admin > Roles never signing in, and no limit on wrong passwords.
 * Then two-factor for every admin, sessions that end (idle, switched off, a
 * new role or password, a reset two-factor), the sign-in history, the
 * new-browser e-mail, and 12-character admin passwords.
 */
class AdminSignInTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret#Pass2026';

    private const RECOVERY = 'AAAAA-BBBBB';

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1.
        $this->seed(RoleSeeder::class);

        Mail::fake();
    }

    private function admin(string $role = 'staff'): User
    {
        return User::create([
            'role_id'    => Role::where('slug', $role)->value('id'),
            'first_name' => 'Nadia', 'last_name' => 'Karim', 'email' => 'nadia.'.uniqid().'@izsuite.test',
            'password'   => self::PASSWORD, 'email_verified_at' => now(), 'status' => 'active',
        ]);
    }

    private function superAdmin(): User
    {
        $user = $this->admin('staff');
        $user->forceFill(['role_id' => Role::SUPER_ADMIN_ID])->save();

        return $user->fresh();
    }

    /** Two-factor on for $user, with one recovery code: its secret. */
    private function withTwoFactor(User $user): string
    {
        $secret = Totp::secret();

        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => [RecoveryCodes::hash(self::RECOVERY)]])->save();

        return $secret;
    }

    private function code(string $secret): string
    {
        return Totp::code($secret, Totp::step());
    }

    /** A code no step near now makes. */
    private function wrongCode(string $secret): string
    {
        $near = array_map(fn (int $step) => Totp::code($secret, $step), range(Totp::step() - 1, Totp::step() + 1));

        return collect(range(0, 20))->map(fn (int $n) => str_pad((string) $n, 6, '0', STR_PAD_LEFT))->first(fn (string $code) => ! in_array($code, $near, true));
    }

    private function signIn(User $user, string $password = self::PASSWORD)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => $password]);
    }

    /**
     * As a new request does: the signed-in account read again from the
     * database, not the one a test's guard kept from the last request.
     */
    private function nextRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /** Signed in all the way, with two-factor on. */
    private function signInFully(User $user, string $secret)
    {
        $this->signIn($user)->assertRedirect(route('login.two-factor'));

        return $this->post(route('login.two-factor'), ['code' => $this->code($secret)]);
    }

    /* ---------------------------------------------------------- the faults */

    public function test_a_switched_off_admin_cannot_sign_in_and_is_signed_out(): void
    {
        $member = $this->admin();
        $secret = $this->withTwoFactor($member);
        $member->forceFill(['status' => 'inactive'])->save();

        $this->signIn($member)->assertSessionHasErrors(['email' => AdminSessionService::SWITCHED_OFF]);
        $this->assertGuest();
        $this->assertDatabaseHas('admin_sign_ins', ['user_id' => $member->id, 'succeeded' => false, 'failure' => 'switched_off']);

        // Signed in, then switched off: the next request signs them out.
        $member->forceFill(['status' => 'active'])->save();
        $this->signInFully($member, $secret)->assertRedirect(route('admin.overview'));
        $member->forceFill(['status' => 'inactive'])->save();

        $this->nextRequest();
        $this->get(route('admin.overview'))->assertRedirect(route('login'))->assertSessionHasErrors(['email' => AdminSessionService::SWITCHED_OFF]);
        $this->assertGuest();
    }

    public function test_switching_staff_off_ends_their_sessions_but_nobody_switches_themselves_off(): void
    {
        $member = $this->admin();
        $boss   = $this->admin('admin');

        $this->actingAs($boss)->patchJson(route('admin.staff.toggle-status', $boss->id))
            ->assertStatus(400)->assertJsonPath('message', 'You cannot switch yourself off.');

        $this->patchJson(route('admin.staff.toggle-status', $member->id))->assertOk();
        $this->assertSame('inactive', $member->fresh()->status);
        $this->assertNotNull($member->fresh()->sessions_valid_from);
    }

    public function test_a_role_made_in_admin_roles_signs_in_and_opens_its_pages(): void
    {
        $role   = app(RoleService::class)->create(['name' => 'Content Editor', 'permissions' => ['admin.faqs.index']]);
        $member = $this->admin($role->slug);
        $secret = $this->withTwoFactor($member);

        $this->signInFully($member, $secret)->assertRedirect(route('admin.overview'));
        $this->assertAuthenticatedAs($member);

        $this->get(route('admin.faqs.index'))->assertOk();
        $this->get(route('admin.staff.index'))->assertForbidden();

        // Their own account, whatever their role.
        $this->get(route('admin.profile'))->assertOk();
    }

    public function test_admin_roles_hold_admin_roles_only_and_keep_their_slugs(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.roles.index'))->assertOk()
            ->assertViewHas('roles', fn ($roles) => $roles->pluck('slug')->intersect(Role::FRONTEND_SLUGS)->isEmpty());
        $this->get(route('admin.roles.edit', Role::where('slug', 'user')->value('id')))->assertNotFound();

        // A website account's name is refused: its holders would be admins.
        $this->postJson(route('admin.roles.store'), ['name' => 'USER'])->assertJsonValidationErrors('name');
        $this->postJson(route('admin.roles.store'), ['name' => 'Billing Admin'])->assertOk();
        $this->postJson(route('admin.roles.store'), ['name' => 'Billing-Admin'])
            ->assertJsonValidationErrors(['name' => 'A role with this name exists already. Choose another.']);

        // Renamed, a role keeps its slug.
        $role = Role::where('slug', 'billing-admin')->firstOrFail();
        $this->put(route('admin.roles.update', $role->id), ['name' => 'Finance Desk'])->assertRedirect(route('admin.roles.index'));
        $this->assertSame(['Finance Desk', 'billing-admin'], [$role->fresh()->name, $role->fresh()->slug]);
    }

    public function test_wrong_passwords_are_limited(): void
    {
        $member = $this->admin();

        foreach (range(1, AdminLoginService::MAX_ATTEMPTS) as $try) {
            $this->signIn($member, 'Wrong#Pass2026')->assertSessionHasErrors(['email' => AdminLoginService::WRONG_PASSWORD]);
        }

        // Even the right password waits now.
        $this->signIn($member)->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringStartsWith('Too many sign-in attempts.', session('errors')->first('email'));
        $this->assertSame([AdminLoginService::MAX_ATTEMPTS, 1], [AdminSignIn::where('failure', 'password')->count(), AdminSignIn::where('failure', 'throttled')->count()]);

        $this->travel(61)->seconds();
        $this->signIn($member)->assertRedirect(route('admin.two-factor.setup'));
    }

    /* ---------------------------------------------------------- two-factor */

    public function test_an_admin_without_two_factor_signs_in_and_sets_it_up_first(): void
    {
        $member = $this->admin();

        $this->signIn($member)->assertRedirect(route('admin.two-factor.setup'));
        $this->assertAuthenticatedAs($member);

        // The rest of the panel waits for it.
        $this->get(route('admin.overview'))->assertRedirect(route('admin.two-factor.setup'));
        $this->getJson(route('admin.overview'))->assertForbidden()->assertJsonPath('message', 'Set up two-factor sign-in first.');

        $page   = $this->get(route('admin.two-factor.setup'))->assertOk();
        $secret = $member->fresh()->two_factor_secret;
        $page->assertSee(trim(chunk_split($secret, 4, ' ')));

        // Opened again: the same secret, nothing to scan again.
        $this->get(route('admin.two-factor.setup'))->assertOk();
        $this->assertSame($secret, $member->fresh()->two_factor_secret);

        $this->post(route('admin.two-factor.confirm'), ['code' => $this->wrongCode($secret)])->assertSessionHasErrors('code');
        $this->post(route('admin.two-factor.confirm'), ['code' => $this->code($secret)])->assertRedirect(route('admin.two-factor.recovery-codes'));

        $this->assertCount(RecoveryCodes::COUNT, $this->get(route('admin.two-factor.recovery-codes'))->assertOk()->viewData('codes'));
        // Shown once.
        $this->get(route('admin.two-factor.recovery-codes'))->assertRedirect(route('admin.profile'));

        $this->get(route('admin.overview'))->assertOk();
        $this->assertNotNull($member->fresh()->two_factor_confirmed_at);
    }

    public function test_an_admin_with_two_factor_gives_a_code_after_the_password(): void
    {
        $member = $this->admin();
        $secret = $this->withTwoFactor($member);

        $this->signIn($member)->assertRedirect(route('login.two-factor'));
        $this->assertGuest();
        $this->get(route('login.two-factor'))->assertOk()->assertSee('Two-factor sign-in');

        $this->post(route('login.two-factor'), ['code' => $this->wrongCode($secret)])->assertSessionHasErrors(['code' => AdminLoginService::WRONG_CODE]);
        $this->assertGuest();

        $this->post(route('login.two-factor'), ['code' => $this->code($secret)])->assertRedirect(route('admin.overview'));
        $this->assertAuthenticatedAs($member);

        // A recovery code works once, however it is typed.
        $this->post('/logout');
        $this->signIn($member);
        $this->post(route('login.two-factor'), ['recovery_code' => 'aaaaa bbbbb'])->assertRedirect(route('admin.overview'));
        $this->assertSame([], $member->fresh()->two_factor_recovery_codes);
    }

    public function test_five_wrong_codes_or_five_minutes_end_the_code_step(): void
    {
        $member = $this->admin();
        $secret = $this->withTwoFactor($member);

        $this->signIn($member);

        foreach (range(1, AdminLoginService::CODE_TRIES - 1) as $try) {
            $this->post(route('login.two-factor'), ['code' => $this->wrongCode($secret)])->assertSessionHasErrors('code');
        }

        $this->post(route('login.two-factor'), ['code' => $this->wrongCode($secret)])->assertSessionHasErrors(['email' => AdminLoginService::TOO_MANY_CODES]);
        $this->get(route('login.two-factor'))->assertRedirect(route('login'));
        $this->post(route('login.two-factor'), ['code' => $this->code($secret)])->assertRedirect(route('login'));
        $this->assertGuest();

        $this->signIn($member);
        $this->travel(AdminLoginService::CODE_MINUTES + 1)->minutes();
        $this->post(route('login.two-factor'), ['code' => $this->code($secret)])
            ->assertRedirect(route('login'))->assertSessionHasErrors(['email' => AdminLoginService::CODE_EXPIRED]);
        $this->assertGuest();
    }

    public function test_only_a_super_admin_resets_a_lost_two_factor_and_it_ends_their_sessions(): void
    {
        $super  = $this->superAdmin();
        $member = $this->admin();
        $secret = $this->withTwoFactor($member);

        $this->signInFully($member, $secret);
        $this->get(route('admin.overview'))->assertOk();

        // Reset as the Super Admin does it: their open session ends.
        $this->travel(1)->seconds();
        app(AdminTwoFactorService::class)->reset($super, $member, self::PASSWORD, 'Lost their phone');

        $this->nextRequest();
        $this->get(route('admin.overview'))->assertRedirect(route('login'))->assertSessionHasErrors(['email' => AdminSessionService::ENDED]);
        $this->assertNull($member->fresh()->two_factor_confirmed_at);

        // Signing in again, they set it up anew.
        $this->signIn($member)->assertRedirect(route('admin.two-factor.setup'));
        $this->post('/logout');
        $this->nextRequest();

        // Through the page: a role allowed the page is still not a Super Admin.
        $role   = app(RoleService::class)->create(['name' => 'Support Desk', 'permissions' => ['admin.staff.two-factor.reset']]);
        $helper = $this->admin($role->slug);
        $other  = $this->admin();
        $this->withTwoFactor($other);

        $this->actingAs($helper)->postJson(route('admin.staff.two-factor.reset', $other->id))
            ->assertForbidden()->assertJsonPath('message', 'Only a Super Admin can reset two-factor sign-in.');
        $this->assertNotNull($other->fresh()->two_factor_confirmed_at);

        $this->actingAs($super)->postJson(route('admin.staff.two-factor.reset', $other->id), ['confirm_password' => self::PASSWORD, 'reason' => 'Lost their phone'])->assertOk();
        $this->assertNull($other->fresh()->two_factor_confirmed_at);
    }

    /* ---------------------------------------------------------- sessions */

    public function test_an_idle_session_ends_after_thirty_minutes(): void
    {
        $member = $this->admin();
        $this->signInFully($member, $this->withTwoFactor($member));

        // Each request starts the clock again.
        $this->travel(AdminSessionService::IDLE_MINUTES - 1)->minutes();
        $this->get(route('admin.overview'))->assertOk();
        $this->travel(AdminSessionService::IDLE_MINUTES - 1)->minutes();
        $this->get(route('admin.overview'))->assertOk();

        $this->travel(AdminSessionService::IDLE_MINUTES + 1)->minutes();
        $this->get(route('admin.overview'))->assertRedirect(route('login'))->assertSessionHasErrors(['email' => AdminSessionService::IDLE]);
        $this->assertGuest();
    }

    public function test_a_new_role_ends_their_sessions_and_their_own_new_password_ends_the_others(): void
    {
        $member = $this->admin();
        $this->signInFully($member, $this->withTwoFactor($member));

        $this->put(route('admin.profile.password'), ['current_password' => self::PASSWORD, 'password' => 'Newer#Pass2026', 'password_confirmation' => 'Newer#Pass2026'])->assertSessionHasNoErrors();
        $this->assertNotNull($member->fresh()->sessions_valid_from);
        $this->get(route('admin.overview'))->assertOk();

        $this->travel(1)->seconds();
        app(StaffService::class)->update($member->fresh(), ['first_name' => 'Nadia', 'email' => $member->email, 'role_id' => Role::where('slug', 'admin')->value('id')]);

        $this->nextRequest();
        $this->get(route('admin.overview'))->assertRedirect(route('login'))->assertSessionHasErrors(['email' => AdminSessionService::ENDED]);
    }

    /* ---------------------------------------------------------- history, e-mail, passwords */

    public function test_sign_ins_and_failed_attempts_are_kept_and_shown(): void
    {
        $member = $this->admin();
        $secret = $this->withTwoFactor($member);

        $this->signIn($member, 'Wrong#Pass2026');
        $this->signInFully($member, $secret);

        $history = AdminSignIn::where('user_id', $member->id)->orderBy('id')->get();
        $this->assertSame([[false, 'password'], [true, null]], $history->map(fn (AdminSignIn $signIn) => [$signIn->succeeded, $signIn->failure])->all());
        $this->assertSame('127.0.0.1', $history[1]->ip_address);
        $this->assertNotNull($history[1]->device);

        $this->get(route('admin.profile'))->assertOk()->assertSee('Recent Sign-Ins')->assertSee('Wrong e-mail or password');

        $this->actingAs($this->superAdmin())->get(route('admin.staff.edit', $member->id))->assertOk()
            ->assertSee('Recent Sign-Ins')->assertSee('Two-Factor Sign-In')->assertSee('Reset Two-Factor Sign-In');
    }

    public function test_a_sign_in_from_a_new_browser_is_emailed(): void
    {
        $member = $this->admin();
        $secret = $this->withTwoFactor($member);

        // The first sign-in of all has nothing to compare with.
        $device = $this->signInFully($member, $secret)->getCookie(AdminLoginService::DEVICE_COOKIE)->getValue();
        Mail::assertNothingQueued();

        // The same browser again: nothing.
        $this->post('/logout');
        $this->travel(31)->seconds();
        $this->withCookie(AdminLoginService::DEVICE_COOKIE, $device);
        $this->signInFully($member, $secret)->assertRedirect(route('admin.overview'));
        Mail::assertNothingQueued();

        // Another browser: e-mailed.
        $this->post('/logout');
        $this->travel(31)->seconds();
        $this->defaultCookies = [];
        $this->signInFully($member, $secret)->assertRedirect(route('admin.overview'));
        Mail::assertQueued(AdminNewSignIn::class, fn (AdminNewSignIn $mail) => $mail->hasTo($member->email) && $mail->ipAddress === '127.0.0.1');
    }

    public function test_admin_passwords_are_twelve_characters_at_least(): void
    {
        $this->actingAs($this->superAdmin());

        $store = fn (string $password) => $this->postJson(route('admin.staff.store'), [
            'first_name' => 'Rafi', 'email' => 'rafi@izsuite.test', 'password' => $password, 'password_confirmation' => $password,
            'role_id'    => Role::where('slug', 'staff')->value('id'),
        ]);

        $store('Short#Pw026')->assertJsonValidationErrors('password');
        $store(self::PASSWORD)->assertOk();
    }
}
