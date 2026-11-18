<?php

namespace Tests\Feature;

use App\Mail\SubscriptionNoticeMail;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\WorkspaceCard;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\Concerns\PricesPlans;
use Tests\TestCase;

/**
 * The saved card charged without the business there (Doc 13 sections 12 and
 * 15) - Doc 13 Phase D3: on the renewal's due day and the trial's last day,
 * again on the grace's retry days and its last, once a day; a refusal
 * e-mailed and shown as Past Due; the bank asking the cardholder to confirm;
 * paying with the saved card at once; a processing charge settled by
 * Stripe's notice. Stripe's API is faked.
 */
class SubscriptionAutoChargeTest extends TestCase
{
    use PricesPlans;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SIGNING = 'whsec_test_signing';

    private User $owner;

    private Workspace $business;

    /** @var list<string> what Stripe answers the next charges: succeeded, declined, action or processing */
    private array $answers = [];

    /** @var array<string, array> the charges Stripe made, by id */
    private array $intents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));
        $this->fakeStripe();

        Package::where('key', 'standard')->update(['grace_days' => 5]);
        $this->priced('standard', '19.50', '195.00');
        Package::where('key', 'premium')->update(['trial_days' => 14]);
        $this->priced('premium', '49.00', '490.00');
        PaymentMethod::where('slug', 'stripe')->firstOrFail()->update([
            'is_active'   => true, 'is_sandbox' => true,
            'credentials' => ['test' => ['publishable_key' => 'pk_test_123', 'secret_key' => 'sk_test_123', 'webhook_secret' => self::SIGNING]],
        ]);

        $this->owner    = $this->signIn();
        $this->business = $this->businessOf($this->owner);
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Uddin Traders Ltd', 'email' => 'accounts@uddin.example', 'address' => 'Dhaka', 'country' => 'BD'])->assertOk();
    }

    private function fakeStripe(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_contains($path, '/forms/chromium')) {
                return Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf']);
            }

            if ($path === '/v1/payment_intents') {
                $id                 = 'pi_'.(count($this->intents) + 1);
                $answer             = array_shift($this->answers) ?? 'succeeded';
                $intent             = ['id' => $id, 'amount_received' => $answer === 'succeeded' ? $request['amount'] : 0, 'currency' => $request['currency'], 'customer' => $request['customer'],
                    'livemode'      => false, 'metadata' => $request['metadata'], 'payment_method' => ['id' => $request['payment_method'], 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 8, 'exp_year' => 2030]]];
                $this->intents[$id] = $intent;

                return match ($answer) {
                    'declined' => Http::response(['error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'Your card has insufficient funds.', 'payment_intent' => [...$intent, 'status' => 'requires_payment_method']]], 402),
                    'action'   => Http::response(['error' => ['type' => 'card_error', 'code' => 'authentication_required', 'message' => 'This payment requires authentication.', 'payment_intent' => [...$intent, 'status' => 'requires_action']]], 402),
                    default    => Http::response([...$intent, 'status' => $answer, 'payment_method' => $request['payment_method']]),
                };
            }

            if (preg_match('#^/v1/payment_intents/(pi_\d+)$#', $path, $match)) {
                return Http::response([...$this->intents[$match[1]], 'status' => 'succeeded', 'amount_received' => 1950]);
            }

            if (preg_match('#^/v1/payment_methods/\w+/detach$#', $path)) {
                return Http::response(['id' => 'pm']);
            }

            return Http::response(['error' => ['message' => "Not faked: {$path}"]], 500);
        });
    }

    private function saveCard(): void
    {
        WorkspaceCard::create([
            'workspace_id' => $this->business->id, 'provider' => 'stripe', 'livemode' => false, 'customer_id' => 'cus_uddin',
            'card_id'      => 'pm_visa', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 8, 'exp_year' => 2030,
        ]);
    }

    /** Standard, monthly, paid on 9 Nov: 9 Nov to 8 Dec - its renewal comes on 1 Dec, due 8 Dec. */
    private function active(): void
    {
        $id = $this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'])->assertCreated()->json('data.open_invoice.id');
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]))->post(route('admin.subscription-invoices.confirm', $id))->assertRedirect();
        Sanctum::actingAs($this->owner);
    }

    private function on(string $day): void
    {
        $this->travelTo(Carbon::parse("{$day} 10:00:00", 'UTC'));
        $this->artisan('subscriptions:advance')->assertSuccessful();
    }

    private function charges(): int
    {
        return count($this->intents);
    }

    private function renewal(): SubscriptionInvoice
    {
        return SubscriptionInvoice::where('kind', 'renewal')->latest('id')->firstOrFail();
    }

    public function test_the_renewal_is_charged_to_the_saved_card_on_its_due_day_once(): void
    {
        $this->active();
        $this->saveCard();

        $this->on('2026-12-01');
        $this->assertSame([0, 'open'], [$this->charges(), $this->renewal()->status]);

        $this->on('2026-12-08');
        $this->on('2026-12-08');

        $renewal = $this->renewal();

        $this->assertSame([1, 'paid', 'Visa ending 4242', 'pi_1'], [$this->charges(), $renewal->status, $renewal->paid_with, $renewal->payment_reference]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/payment_intents'
            && $request['off_session'] === 'true' && $request['confirm'] === 'true' && $request['customer'] === 'cus_uddin' && $request['payment_method'] === 'pm_visa'
            && $request['amount'] === 1950 && $request->header('Idempotency-Key') === ["izsuite-charge-{$renewal->id}-2026-12-08"]);

        // Paid ahead: the next period starts as this one ends.
        $this->on('2026-12-09');
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'active')->assertJsonPath('data.subscription.period_ends_on', '2027-01-08');
    }

    public function test_a_declined_card_is_past_due_then_tried_again_in_grace(): void
    {
        $this->answers = ['declined', 'declined', 'succeeded'];
        $this->active();
        $this->saveCard();
        $this->on('2026-12-01');

        $this->on('2026-12-08');
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'past_due')
            ->assertJsonPath('data.last_charge', ['status' => 'failed', 'card' => 'Visa ending 4242', 'reason' => 'Your card has insufficient funds.', 'on' => '2026-12-08']);
        Mail::assertQueued(SubscriptionNoticeMail::class, fn ($mail) => $mail->kind === SubscriptionNoticeMail::PAYMENT_FAILED && $mail->details['declined'] === 'Your card has insufficient funds.' && $mail->details['for'] === 'renewal');

        // Grace day 1: tried again, declined - the day's grace e-mail says so. Day 2: not tried.
        $this->on('2026-12-09');
        $this->on('2026-12-10');
        $this->assertSame(2, $this->charges());
        Mail::assertQueued(SubscriptionNoticeMail::class, fn ($mail) => $mail->kind === SubscriptionNoticeMail::GRACE && ($mail->details['card'] ?? null) === 'Visa ending 4242' && $mail->details['days_left'] === 4);
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'grace');

        // Day 3: taken - the plan carries on from where its period ended.
        $this->on('2026-12-11');
        $this->assertSame(3, $this->charges());
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'active')->assertJsonPath('data.last_charge', null)
            ->assertJsonPath('data.subscription.period_starts_on', '2026-12-09');

        $this->actingAs(User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]))->get(route('admin.subscription-invoices.index'))
            ->assertSeeInOrder(['Dec 08 · Visa ending 4242:', 'declined. Your card has insufficient funds.', 'Dec 09 · Visa ending 4242:', 'declined.']);
    }

    public function test_a_bank_asking_to_confirm_sends_the_business_to_stripes_page(): void
    {
        $this->answers = ['action', 'action'];
        $this->active();
        $this->saveCard();
        $this->on('2026-12-01');
        $this->on('2026-12-08');

        Mail::assertQueued(SubscriptionNoticeMail::class, fn ($mail) => $mail->kind === SubscriptionNoticeMail::PAYMENT_FAILED && $mail->details['confirm'] === true);
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.last_charge.status', 'action_required');

        $this->postJson("/api/v1/subscription/invoices/{$this->renewal()->id}/charge")->assertStatus(409)
            ->assertJsonPath('message', "Your bank asks you to confirm this payment: use Pay by Card to confirm it on Stripe's page.");
        $this->assertSame(2, SubscriptionPayment::where('status', 'action_required')->count());
    }

    public function test_a_trial_is_charged_on_its_last_day(): void
    {
        // Premium's trial: 9 Nov to 22 Nov; its invoice is due on the 22nd.
        $this->postJson('/api/v1/subscription', ['package' => 'premium', 'cycle' => 'monthly'])->assertCreated();
        $this->saveCard();

        $this->on('2026-11-21');
        $this->assertSame(0, $this->charges());

        $this->on('2026-11-22');
        $this->on('2026-11-23');

        $this->assertSame(1, $this->charges());
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.subscription.period_starts_on', '2026-11-23')->assertJsonPath('data.subscription.period_ends_on', '2026-12-22');
        $this->assertSame('premium', $this->business->fresh()->package->key);
    }

    public function test_the_business_pays_with_its_saved_card_and_without_one_nothing_is_charged(): void
    {
        $id = $this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'])->assertCreated()->json('data.open_invoice.id');

        $this->postJson("/api/v1/subscription/invoices/{$id}/charge")->assertStatus(409)->assertJsonPath('message', "There is no saved card. Pay by card on Stripe's page, or by bank transfer.");

        $this->saveCard();
        $this->postJson("/api/v1/subscription/invoices/{$id}/charge")->assertOk()
            ->assertJsonPath('message', 'Thank you: invoice IZS-INV-00001 is paid by Visa ending 4242. Receipt IZS-RCT-00001 is on its way by e-mail.')
            ->assertJsonPath('data.status', 'active');

        // The card removed: the renewal waits to be paid.
        $this->deleteJson('/api/v1/subscription/card')->assertOk();
        $this->on('2026-12-01');
        $this->on('2026-12-08');

        $this->assertSame([1, 'open'], [$this->charges(), $this->renewal()->status]);
    }

    public function test_a_processing_charge_is_settled_by_stripes_notice(): void
    {
        $this->answers = ['processing'];
        $this->active();
        $this->saveCard();
        $this->on('2026-12-01');
        $this->on('2026-12-08');

        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'active')->assertJsonPath('data.last_charge.status', 'processing');

        $payload = json_encode(['id' => 'evt_1', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_1', 'object' => 'payment_intent']]]);
        $time    = now()->timestamp;

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", self::SIGNING), 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], $payload)->assertOk();

        // The card's payment - the first invoice's transfer is a payment too (Doc 15 Phase C2a).
        $this->assertSame(['paid', 'succeeded'], [$this->renewal()->status, SubscriptionPayment::where('provider', 'stripe')->sole()->status]);
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.last_charge', null);
    }
}
