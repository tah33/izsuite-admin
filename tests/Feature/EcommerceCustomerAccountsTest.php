<?php

namespace Tests\Feature;

use App\Mail\StoreCustomerCodeMail;
use App\Models\Shared\ActivityLog;
use App\Models\User\StorefrontCustomer;
use App\Models\User\StorefrontCustomerCode;
use App\Models\User\StorefrontCustomerToken;
use App\Models\User\User;
use App\Repositories\User\EcommerceSettingRepository;
use App\Services\Api\Ecommerce\StorefrontCustomerAuth;
use App\Services\Api\Ecommerce\StorefrontCustomerCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase E1: a storefront customer's account (section 19.16) - signing
 * up, verifying the e-mail by its code, signing in and out, a forgotten
 * password, the profile, a new password and closing it - by a sign-in of its
 * own, apart from the business's users.
 */
class EcommerceCustomerAccountsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const PASSWORD = 'Secret#2026a';

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront([]);
        Mail::fake();
    }

    public function test_a_customer_signs_up_and_once_verified_is_signed_in(): void
    {
        $users = User::count();

        $answer = $this->signUp()->assertCreated()->assertJsonPath('message', 'Your account is made: verify your e-mail with the code we sent.');
        $answer->assertExactJson([
            'message' => 'Your account is made: verify your e-mail with the code we sent.',
            'data'    => ['token' => null, 'verify' => true, 'customer' => ['name' => 'Karim Rahman', 'email' => 'karim@example.test', 'phone' => '+8801700000001', 'verified' => false, 'since' => now()->toDateString()]],
        ]);

        // The business's account - never one of its users.
        $customer = StorefrontCustomer::sole();
        $this->assertSame([$this->business->id, $this->store->id, StorefrontCustomer::ACTIVE], [$customer->workspace_id, $customer->storefront_id, $customer->status]);
        $this->assertSame($users, User::count());
        $this->assertSame('Karim Rahman signed up on Rahman Shop', ActivityLog::where('model_type', StorefrontCustomer::class)->sole()->description);

        Mail::assertSent(StoreCustomerCodeMail::class, fn (StoreCustomerCodeMail $mail) => $mail->hasTo('karim@example.test')
            && $mail->envelope()->subject === 'Your code to verify your e-mail at Rahman Shop'
            && $mail->hasFrom((string) config('mail.from.address'), 'Rahman Shop')
            && $mail->hasReplyTo('help@rahman.test')
            && str_contains($mail->render(), $mail->code)
            && preg_match('/^\d{6}$/', $mail->code) === 1);

        // Not verified, it cannot sign in - and a code sent a moment ago is not sent again.
        $this->signInAs()->assertForbidden()->assertExactJson(['message' => 'Verify your e-mail first: we have sent you a code.', 'verify' => true]);
        Mail::assertSentCount(1);

        $this->postJson(self::STORE.'/account/verify', ['email' => 'karim@example.test', 'code' => $this->wrong($this->lastCode())])
            ->assertUnprocessable()->assertJsonPath('errors.code.0', StorefrontCustomerCodes::WRONG);

        $token = $this->postJson(self::STORE.'/account/verify', ['email' => 'KARIM@example.test', 'code' => $this->lastCode()])
            ->assertOk()->assertJsonPath('data.verify', false)->assertJsonPath('data.customer.verified', true)->json('data.token');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);
        $this->assertSame(hash('sha256', $token), StorefrontCustomerToken::sole()->token_hash);

        // Phase E3: with its language - none chosen yet - its marketing, and the store's languages.
        $this->as($token)->getJson(self::STORE.'/account')->assertOk()->assertExactJson([
            'data' => [
                'name' => 'Karim Rahman', 'email' => 'karim@example.test', 'phone' => '+8801700000001', 'verified' => true, 'since' => now()->toDateString(),
                'language' => null, 'marketing' => false, 'languages' => [['code' => 'en', 'name' => 'English']],
            ],
        ]);

        // Verified, it is not verified again.
        $this->postJson(self::STORE.'/account/verify', ['email' => 'karim@example.test', 'code' => $this->lastCode()])
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'This e-mail is verified already: sign in.');
    }

    public function test_without_verified_e_mails_asked_signing_up_signs_in_at_once(): void
    {
        app(EcommerceSettingRepository::class)->save($this->business, ['verify_customer_email' => false]);

        $token = $this->signUp()->assertCreated()->assertJsonPath('message', 'Your account is ready.')
            ->assertJsonPath('data.verify', false)->assertJsonPath('data.customer.verified', false)->json('data.token');
        $this->as($token)->getJson(self::STORE.'/account')->assertOk();

        // Its e-mail is still sent a code to verify it, and it signs in unverified.
        Mail::assertSent(StoreCustomerCodeMail::class, 1);
        $this->signInAs()->assertOk()->assertJsonPath('data.customer.verified', false);
    }

    public function test_an_e_mail_has_one_account_which_closed_opens_again(): void
    {
        $this->verifiedAccount();

        $this->signUp(['email' => 'Karim@Example.test'])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'An account uses this e-mail already: sign in, or reset its password.');

        $this->as($this->signInAs()->json('data.token'))->deleteJson(self::STORE.'/account', ['password' => self::PASSWORD])->assertOk();

        // Signed up again: the same account, opened - to be verified again, with the new password.
        $this->signUp(['name' => 'Karim R.', 'password' => 'Another#2026', 'password_confirmation' => 'Another#2026'])->assertCreated()->assertJsonPath('data.customer.verified', false);
        $customer = StorefrontCustomer::sole();
        $this->assertSame([StorefrontCustomer::ACTIVE, null, null, 'Karim R.'], [$customer->status, $customer->closed_at, $customer->email_verified_at, $customer->name]);
        $this->assertSame('Karim R. opened their account again on Rahman Shop', ActivityLog::where('model_type', StorefrontCustomer::class)->latest('id')->first()->description);

        $this->postJson(self::STORE.'/account/verify', ['email' => 'karim@example.test', 'code' => $this->lastCode()])->assertOk();
        $this->signInAs('karim@example.test', 'Another#2026')->assertOk();
    }

    public function test_signing_up_asks_for_a_name_an_e_mail_and_a_good_password(): void
    {
        $this->postJson(self::STORE.'/account', ['email' => 'not an e-mail', 'password' => 'short', 'password_confirmation' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->signUp(['password' => 'alllowercase1!', 'password_confirmation' => 'alllowercase1!'])->assertJsonValidationErrors('password');
        $this->assertSame(0, StorefrontCustomer::count());

        // A store not open takes no one.
        $this->store->update(['status' => 'draft']);
        $this->signUp()->assertNotFound();
    }

    public function test_signing_in_and_out(): void
    {
        $this->verifiedAccount();

        $this->signInAs('karim@example.test', 'Wrong#2026a')->assertUnprocessable()->assertJsonPath('errors.email.0', 'The e-mail or the password is not right.');
        $this->signInAs('nobody@example.test')->assertUnprocessable()->assertJsonPath('errors.email.0', 'The e-mail or the password is not right.');

        $answer = $this->signInAs(' KARIM@example.test ')->assertOk()->assertJsonPath('message', 'You are signed in.');
        $token  = $answer->json('data.token');
        $this->assertNotNull(StorefrontCustomer::sole()->last_signed_in_at);

        $this->as($token)->postJson(self::STORE.'/account/sign-out')->assertOk()->assertExactJson(['message' => 'You are signed out.']);
        $this->as($token)->getJson(self::STORE.'/account')->assertUnauthorized()->assertExactJson(['message' => 'Sign in to your account.']);
        $this->as(null)->getJson(self::STORE.'/account')->assertUnauthorized();
    }

    public function test_five_wrong_tries_in_a_minute_wait(): void
    {
        $this->verifiedAccount();

        foreach (range(1, 5) as $try) {
            $this->signInAs('karim@example.test', 'Wrong#2026a')->assertUnprocessable();
        }

        $this->signInAs()->assertStatus(429)->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Too many tries: try again in'));

        $this->travel(61)->seconds();
        $this->signInAs()->assertOk();
    }

    public function test_a_forgotten_password_is_reset_by_its_code(): void
    {
        $this->signUp()->assertCreated();
        $customer = StorefrontCustomer::sole();
        $earlier  = app(StorefrontCustomerAuth::class)->signIn($customer);

        // Said the same for an e-mail with no account - and nothing sent.
        $this->postJson(self::STORE.'/account/password/forgot', ['email' => 'nobody@example.test'])->assertOk()
            ->assertExactJson(['message' => 'If an account uses this e-mail, a code to choose a new password is on its way.']);
        $this->postJson(self::STORE.'/account/password/forgot', ['email' => 'Karim@example.test'])->assertOk();
        Mail::assertSent(StoreCustomerCodeMail::class, fn (StoreCustomerCodeMail $mail) => $mail->purpose === StorefrontCustomerCode::RESET
            && $mail->envelope()->subject === 'Your code to reset your password at Rahman Shop');

        // Asked again within the minute: no second code.
        $this->postJson(self::STORE.'/account/password/forgot', ['email' => 'karim@example.test'])->assertOk();
        $this->assertCount(1, Mail::sent(StoreCustomerCodeMail::class, fn (StoreCustomerCodeMail $mail) => $mail->purpose === StorefrontCustomerCode::RESET));

        $code   = $this->lastCode(StorefrontCustomerCode::RESET);
        $reset  = ['email' => 'karim@example.test', 'code' => $code, 'password' => 'Another#2026', 'password_confirmation' => 'Another#2026'];
        $this->postJson(self::STORE.'/account/password/reset', [...$reset, 'code' => $this->wrong($code)])->assertUnprocessable()->assertJsonPath('errors.code.0', StorefrontCustomerCodes::WRONG);
        $this->postJson(self::STORE.'/account/password/reset', ['email' => 'nobody@example.test', ...array_diff_key($reset, ['email' => 1])])->assertUnprocessable();

        // The new password saved: the e-mail verified with it, the earlier sign-in signed out, and signed in.
        $token = $this->postJson(self::STORE.'/account/password/reset', $reset)->assertOk()->assertJsonPath('data.customer.verified', true)->json('data.token');
        $this->as($earlier)->getJson(self::STORE.'/account')->assertUnauthorized();
        $this->as($token)->getJson(self::STORE.'/account')->assertOk();
        $this->signInAs()->assertUnprocessable();
        $this->signInAs('karim@example.test', 'Another#2026')->assertOk();

        // Its code is spent.
        $this->postJson(self::STORE.'/account/password/reset', $reset)->assertUnprocessable()->assertJsonPath('errors.code.0', StorefrontCustomerCodes::WRONG);
    }

    public function test_a_code_holds_thirty_minutes_and_five_wrong_tries(): void
    {
        $this->signUp()->assertCreated();
        $code = $this->lastCode();

        // A new code waits a minute after the last.
        $this->postJson(self::STORE.'/account/verify/resend', ['email' => 'karim@example.test'])->assertStatus(429)
            ->assertJsonPath('message', fn (string $message) => (bool) preg_match('/^Ask for a new code in \d+ seconds\.$/', $message));

        // Five wrong tries spend it: the right digits are then wrong too.
        foreach (range(1, 5) as $try) {
            $this->postJson(self::STORE.'/account/verify', ['email' => 'karim@example.test', 'code' => $this->wrong($code)])->assertUnprocessable();
        }
        $this->postJson(self::STORE.'/account/verify', ['email' => 'karim@example.test', 'code' => $code])->assertJsonPath('errors.code.0', StorefrontCustomerCodes::WRONG);

        // A new one, a minute on - the earlier spent - holds for 30 minutes.
        $this->travel(61)->seconds();
        $this->postJson(self::STORE.'/account/verify/resend', ['email' => 'karim@example.test'])->assertOk()
            ->assertExactJson(['message' => 'If an account waits for this e-mail to be verified, a new code is on its way.']);
        $fresh = $this->lastCode();
        $this->travel(31)->minutes();
        $this->postJson(self::STORE.'/account/verify', ['email' => 'karim@example.test', 'code' => $fresh])->assertUnprocessable()->assertJsonPath('errors.code.0', StorefrontCustomerCodes::EXPIRED);

        // An e-mail with no account waiting is told the same, and sent nothing.
        $sent = Mail::sent(StoreCustomerCodeMail::class)->count();
        $this->postJson(self::STORE.'/account/verify/resend', ['email' => 'nobody@example.test'])->assertOk();
        $this->assertSame($sent, Mail::sent(StoreCustomerCodeMail::class)->count());
    }

    public function test_the_profile_a_new_password_and_closing_the_account(): void
    {
        $this->verifiedAccount();
        $token = $this->signInAs()->json('data.token');
        $other = $this->signInAs()->json('data.token');

        $this->as($token)->putJson(self::STORE.'/account', ['name' => ' Karim U. Rahman ', 'phone' => ''])->assertOk()
            ->assertJsonPath('message', 'Your details are saved.')->assertJsonPath('data.name', 'Karim U. Rahman')->assertJsonPath('data.phone', null);
        $this->as($token)->putJson(self::STORE.'/account', ['email' => 'new@example.test'])->assertJsonValidationErrors('name');
        $this->assertSame('karim@example.test', StorefrontCustomer::sole()->email);

        // A new password, given the current one: the other sign-in signed out, this one kept.
        $password = ['current_password' => 'Wrong#2026a', 'password' => 'Another#2026', 'password_confirmation' => 'Another#2026'];
        $this->as($token)->putJson(self::STORE.'/account/password', $password)->assertUnprocessable()->assertJsonPath('errors.current_password.0', 'This is not your current password.');
        $this->as($token)->putJson(self::STORE.'/account/password', [...$password, 'current_password' => self::PASSWORD])->assertOk();
        $this->as($other)->getJson(self::STORE.'/account')->assertUnauthorized();
        $this->as($token)->getJson(self::STORE.'/account')->assertOk();

        // Closed with its password: kept, closed, signed out everywhere - and not signed in again.
        $this->as($token)->deleteJson(self::STORE.'/account', ['password' => self::PASSWORD])->assertUnprocessable()->assertJsonPath('errors.password.0', 'This is not your password.');
        $this->as($token)->deleteJson(self::STORE.'/account', ['password' => 'Another#2026'])->assertOk()->assertExactJson(['message' => 'Your account is closed.']);
        $customer = StorefrontCustomer::sole();
        $this->assertSame(StorefrontCustomer::CLOSED, $customer->status);
        $this->assertNotNull($customer->closed_at);
        $this->assertSame(0, StorefrontCustomerToken::count());
        $this->as($token)->getJson(self::STORE.'/account')->assertUnauthorized();
        $this->signInAs('karim@example.test', 'Another#2026')->assertUnprocessable()->assertJsonPath('errors.email.0', 'The e-mail or the password is not right.');
        $this->assertSame('Karim U. Rahman closed their account', ActivityLog::where('model_type', StorefrontCustomer::class)->latest('id')->first()->description);
    }

    public function test_a_customer_sign_in_is_its_own_and_its_business_s(): void
    {
        $this->verifiedAccount();
        $token = $this->signInAs()->json('data.token');

        // Another storefront of the business signs them in too; another business's does not.
        $this->postJson('/api/v1/ecommerce/storefronts', [
            'name' => 'Rahman Outlet', 'code' => 'rahman-outlet', 'status' => 'live', 'default_language' => 'en', 'currency' => 'USD', 'countries' => ['BD'], 'location_id' => $this->store->location_id,
        ])->assertCreated();
        $owner = User::factory()->create();
        $other = $this->businessOf($owner);
        $other->storefronts()->create([
            'name' => 'Other Shop', 'code' => 'other-shop', 'status' => 'live', 'default_language' => 'en', 'languages' => ['en'], 'currency' => 'USD', 'currencies' => ['USD'], 'countries' => ['BD'],
            'location_id' => $other->locations()->create(['user_id' => $owner->id, 'name' => 'Sylhet', 'code' => 'SYL'])->id,
        ]);

        $this->as($token)->getJson('/api/v1/stores/rahman-outlet/account')->assertOk();
        $this->as($token)->getJson('/api/v1/stores/other-shop/account')->assertUnauthorized();

        // Never a business user's sign-in, either way.
        $userToken = $this->user->createToken('test')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->as(null)->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->as(null)->withToken($userToken)->getJson(self::STORE.'/account')->assertUnauthorized();
        $this->as($userToken)->getJson(self::STORE.'/account')->assertUnauthorized();
        $this->as(null)->withToken($userToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    /** Karim's sign-up on the store, as $fields change it. */
    private function signUp(array $fields = [])
    {
        return $this->postJson(self::STORE.'/account', [
            'name' => 'Karim Rahman', 'email' => 'Karim@Example.test', 'phone' => '+8801700000001', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, ...$fields,
        ]);
    }

    /** Karim's account, signed up and verified. */
    private function verifiedAccount(): void
    {
        $this->signUp()->assertCreated();
        $this->postJson(self::STORE.'/account/verify', ['email' => 'karim@example.test', 'code' => $this->lastCode()])->assertOk();
    }

    private function signInAs(string $email = 'karim@example.test', string $password = self::PASSWORD)
    {
        return $this->postJson(self::STORE.'/account/sign-in', ['email' => $email, 'password' => $password]);
    }

    /** The calls that follow, signed in by $token - or by nothing: headers set for earlier calls are let go. */
    private function as(?string $token): static
    {
        return $this->flushHeaders()->withHeaders($token ? ['X-Customer-Token' => $token] : []);
    }

    /** The code last e-mailed for $purpose. */
    private function lastCode(string $purpose = StorefrontCustomerCode::VERIFY): string
    {
        return Mail::sent(StoreCustomerCodeMail::class, fn (StoreCustomerCodeMail $mail) => $mail->purpose === $purpose)->last()->code;
    }

    /** Six digits that are not $code. */
    private function wrong(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
