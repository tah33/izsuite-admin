<?php

namespace Tests\Feature;

use App\Mail\SubscriptionNoticeMail;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionRefund;
use App\Models\Billing\WorkspaceCard;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\Shared\UserNotification;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\User\Workspaces\SetUpNewWorkspace;
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
 * Doc 15 Phase C2a (section 14): every payment kept - a bank transfer
 * izSuite confirmed as well as a card's - Admin > Payments and Refunds, an
 * invoice's own page, and refunding a payment: a card's through Stripe,
 * counted once Stripe confirms it, a transfer's recorded by hand; each with
 * the admin's password and a reason, never more than was paid, and the
 * business told. Stripe's API is faked.
 */
class SubscriptionRefundTest extends TestCase
{
    use PricesPlans;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SIGNING = 'whsec_test';

    private User $owner;

    private Workspace $business;

    private User $admin;

    /** @var list<string> what Stripe answers the next charges: succeeded or declined */
    private array $answers = [];

    /** @var list<string> what Stripe answers the next refunds: succeeded, pending or refused */
    private array $refundAnswers = [];

    /** @var array<string, array> Stripe's refunds by id, as it would read them back */
    private array $stripeRefunds = [];

    /** @var list<array{params: array, key: ?string}> the refunds asked of Stripe */
    private array $asked = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));
        $this->fakeStripe();

        Package::where('key', 'standard')->update(['grace_days' => 5]);
        $this->priced('standard', '19.50', '195.00');
        PaymentMethod::where('slug', 'stripe')->firstOrFail()->update([
            'is_active'   => true, 'is_sandbox' => true,
            'credentials' => ['test' => ['publishable_key' => 'pk_test_123', 'secret_key' => 'sk_test_123', 'webhook_secret' => self::SIGNING]],
        ]);

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
        $this->owner    = $this->signIn(User::factory()->create(['first_name' => 'Nadia']));
        $this->business = $this->businessOf($this->owner);
        $this->business->update(['name' => 'Karim Stores']);
        // Its owner a member, as a new business has it: billing's notices reach those who manage the subscription.
        app(SetUpNewWorkspace::class)->handle($this->business, $this->owner, 'api');
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Karim Stores Ltd', 'email' => 'accounts@karim.example', 'address' => 'Dhaka', 'country' => 'BD'])->assertOk();
    }

    private function fakeStripe(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/v1/payment_intents') {
                $answer = array_shift($this->answers) ?? 'succeeded';
                $intent = ['id' => 'pi_'.uniqid(), 'amount_received' => $answer === 'succeeded' ? $request['amount'] : 0, 'currency' => $request['currency'], 'customer' => $request['customer'], 'livemode' => false, 'metadata' => $request['metadata']];

                return $answer === 'declined'
                    ? Http::response(['error' => ['type' => 'card_error', 'code' => 'card_declined', 'message' => 'Your card has insufficient funds.', 'payment_intent' => [...$intent, 'status' => 'requires_payment_method']]], 402)
                    : Http::response([...$intent, 'status' => 'succeeded', 'payment_method' => $request['payment_method']]);
            }

            if ($path === '/v1/refunds') {
                $answer = array_shift($this->refundAnswers) ?? 'succeeded';
                $this->asked[] = ['params' => $request->data(), 'key' => $request->header('Idempotency-Key')[0] ?? null];

                if ($answer === 'refused') {
                    return Http::response(['error' => ['type' => 'invalid_request_error', 'message' => 'This charge has already been refunded.']], 400);
                }

                $id = 're_'.(count($this->stripeRefunds) + 1);

                return Http::response($this->stripeRefunds[$id] = [
                    'id' => $id, 'object' => 'refund', 'amount' => (int) $request['amount'], 'currency' => 'usd', 'status' => $answer,
                    'payment_intent' => $request['payment_intent'], 'metadata' => $request['metadata'] ?? [],
                ]);
            }

            if (preg_match('#^/v1/refunds/(re_\w+)$#', $path, $match) && isset($this->stripeRefunds[$match[1]])) {
                return Http::response($this->stripeRefunds[$match[1]]);
            }

            return Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf']);
        });
    }

    /** Standard, monthly, paid by a bank transfer izSuite confirms on 9 Nov: its period runs to 8 Dec. */
    private function subscribeByTransfer(): SubscriptionInvoice
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'], $this->pick($this->business))->assertCreated()->json('data.open_invoice.id');

        $this->actingAs($this->admin)->post(route('admin.subscription-invoices.confirm', $id))->assertRedirect();

        return SubscriptionInvoice::findOrFail($id);
    }

    /** The renewal, issued on 1 Dec, charged to the saved card on 8 Dec - refused first when $declinedFirst, then taken on the grace's first day. */
    private function renewByCard(bool $declinedFirst = false): SubscriptionPayment
    {
        WorkspaceCard::create(['workspace_id' => $this->business->id, 'provider' => 'stripe', 'livemode' => false, 'customer_id' => 'cus_karim', 'card_id' => 'pm_visa', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 8, 'exp_year' => 2030]);
        $this->answers = $declinedFirst ? ['declined'] : [];

        foreach (['2026-12-01', '2026-12-08', ...($declinedFirst ? ['2026-12-09'] : [])] as $day) {
            $this->travelTo(Carbon::parse("{$day} 10:00:00", 'UTC'));
            $this->artisan('subscriptions:advance')->assertSuccessful();
        }

        return SubscriptionPayment::where('provider', 'stripe')->where('status', SubscriptionPayment::SUCCEEDED)->sole();
    }

    private function refund(SubscriptionPayment $payment, array $data, ?User $admin = null)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($admin ?? $this->admin)->postJson(route('admin.payments.refund', $payment->id), [
            'reason' => 'The owner was charged twice', 'confirm_password' => 'password', ...$data,
        ]);
    }

    private function refundWebhook(string $id, string $type = 'refund.updated')
    {
        $payload = json_encode(['id' => 'evt_'.uniqid(), 'type' => $type, 'data' => ['object' => ['id' => $id, 'object' => 'refund']]]);
        $time    = now()->timestamp;

        $this->app['auth']->forgetGuards();

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", self::SIGNING), 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], $payload);
    }

    private function refundNotices(): int
    {
        return UserNotification::where('user_id', $this->owner->id)->where('trigger', 'subscription.refunded')->count();
    }

    public function test_a_transfer_izsuite_confirms_is_kept_as_a_payment(): void
    {
        $invoice = $this->subscribeByTransfer();
        $payment = SubscriptionPayment::sole();

        $this->assertSame([SubscriptionPayment::TRANSFER, $invoice->number, '19.50', SubscriptionPayment::SUCCEEDED, '2026-11-09'], [$payment->provider, $payment->reference, $payment->amount, $payment->status, $payment->attempted_on->toDateString()]);

        $this->get(route('admin.payments.index'))->assertOk()->assertSeeInOrder(['Karim Stores', $invoice->number, 'Bank transfer', 'USD 19.50', 'Paid']);
        $this->get(route('admin.payments.index', ['method' => 'card']))->assertDontSee($invoice->number);
        $this->get(route('admin.payments.index', ['status' => 'failed']))->assertDontSee($invoice->number);
        $this->get(route('admin.payments.index', ['search' => $invoice->number]))->assertSee('Karim Stores');

        $this->get(route('admin.subscription-invoices.show', $invoice->id))->assertOk()
            ->assertSee('Karim Stores Ltd')->assertSee('Dhaka')
            ->assertSeeInOrder(['Subtotal', 'USD 19.50', 'Total', 'USD 19.50', 'Amount paid', 'USD 19.50', 'Balance', 'USD 0.00'])
            ->assertSee('refund-payment-'.$payment->id);
    }

    public function test_a_transfer_is_refunded_by_hand_with_the_password_and_a_reason(): void
    {
        $invoice = $this->subscribeByTransfer();
        $payment = SubscriptionPayment::sole();

        $this->refund($payment, ['amount' => '10', 'confirm_password' => ''])->assertUnprocessable()->assertJsonValidationErrors(['confirm_password']);
        $this->refund($payment, ['amount' => '10', 'confirm_password' => 'not-it'])->assertUnprocessable()->assertJsonPath('errors.confirm_password.0', 'That password is not right.');
        $this->refund($payment, ['amount' => '25'])->assertUnprocessable()->assertJsonPath('errors.amount.0', 'No more than USD 19.50 is left to refund.');
        $this->assertSame(0, SubscriptionRefund::count());

        $this->refund($payment, ['amount' => '10', 'transfer_reference' => 'BANK-77'])->assertOk()
            ->assertJsonPath('message', 'The refund of USD 10.00 by bank transfer is recorded, and the business is told.');

        $refund = SubscriptionRefund::sole();
        $this->assertSame([SubscriptionRefund::SUCCEEDED, '10.00', 'BANK-77', $this->admin->id, $invoice->id], [$refund->status, $refund->amount, $refund->transfer_reference, $refund->refunded_by, $refund->subscription_invoice_id]);
        $this->assertNotNull($refund->confirmed_at);
        $this->assertSame('The owner was charged twice', AdminAuditLog::where('action', 'refunded')->sole()->reason);
        $this->assertSame(1, $this->refundNotices());
        Mail::assertQueued(SubscriptionNoticeMail::class, fn (SubscriptionNoticeMail $mail) => $mail->kind === SubscriptionNoticeMail::REFUNDED && $mail->details['amount'] === 'USD 10.00' && $mail->hasTo('accounts@karim.example'));

        // The rest, then nothing is left; the invoice and the plan stay as they were.
        $this->refund($payment, ['amount' => '9.50'])->assertOk();
        $this->refund($payment, ['amount' => '1'])->assertUnprocessable()->assertJsonPath('errors.amount.0', 'This payment is refunded in full already.');
        $this->assertSame([SubscriptionInvoice::PAID, WorkspaceSubscription::ACTIVE], [$invoice->fresh()->status, $this->business->subscription->status]);

        $this->get(route('admin.subscription-invoices.show', $invoice->id))->assertSeeInOrder(['Amount paid', 'USD 19.50', 'Refunded', 'USD 19.50'])->assertDontSee('refund-payment-'.$payment->id);
        $this->get(route('admin.refunds.index'))->assertOk()->assertSeeInOrder(['Karim Stores', $invoice->number, 'USD 9.50', 'Refunded', 'The owner was charged twice', 'By Rafi']);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/subscription/invoices', $this->pick($this->business))->assertOk()->assertJsonPath('data.0.refunded', '19.50');
    }

    public function test_a_card_payment_is_refunded_through_stripe_and_counts_once_confirmed(): void
    {
        $this->subscribeByTransfer();
        $payment = $this->renewByCard(declinedFirst: true);

        $this->actingAs($this->admin)->get(route('admin.payments.index', ['status' => 'failed']))->assertSee('Declined')->assertSee('Your card has insufficient funds.');

        // Stripe takes it, but it is still on its way: nothing counts, and nobody is told, yet.
        $this->refundAnswers = ['pending'];
        $this->refund($payment, ['amount' => '19.50'])->assertOk()->assertJsonPath('message', 'The card provider is refunding USD 19.50. It counts once the provider confirms it, and the business is told then.');

        $refund = SubscriptionRefund::sole();
        $this->assertSame([SubscriptionRefund::PENDING, 're_1', null], [$refund->status, $refund->reference, $refund->confirmed_at]);
        $this->assertSame(['payment_intent' => $payment->reference, 'amount' => 1950, 'reason' => 'requested_by_customer'], array_intersect_key($this->asked[0]['params'], array_flip(['payment_intent', 'amount', 'reason'])));
        $this->assertSame("izsuite-refund-{$refund->id}", $this->asked[0]['key']);
        $this->assertSame(0, $this->refundNotices());
        $this->refund($payment, ['amount' => '1'])->assertUnprocessable()->assertJsonPath('errors.amount.0', 'This payment is refunded in full already.');

        // Stripe's word, twice over: done once, told once.
        $this->stripeRefunds['re_1']['status'] = 'succeeded';
        $this->refundWebhook('re_1')->assertOk();
        $this->refundWebhook('re_1')->assertOk();
        $this->assertSame(SubscriptionRefund::SUCCEEDED, $refund->fresh()->status);
        $this->assertNotNull($refund->fresh()->confirmed_at);
        $this->assertSame(1, $this->refundNotices());
    }

    public function test_stripe_refusing_a_refund_is_kept_and_a_dashboard_refund_is_recorded(): void
    {
        $this->subscribeByTransfer();
        $payment = $this->renewByCard();

        $this->refundAnswers = ['refused'];
        $this->refund($payment, ['amount' => '5'])->assertUnprocessable()->assertJsonPath('errors.amount.0', 'Stripe refused: This charge has already been refunded.');
        $this->assertSame([SubscriptionRefund::FAILED, 'Stripe refused: This charge has already been refunded.'], [SubscriptionRefund::sole()->status, SubscriptionRefund::sole()->note]);
        $this->assertSame('refused', AdminAuditLog::latest('id')->first()->action);

        // Made in Stripe's own dashboard: kept against the payment it gives back.
        $this->stripeRefunds['re_dash'] = ['id' => 're_dash', 'amount' => 500, 'currency' => 'usd', 'status' => 'succeeded', 'payment_intent' => $payment->reference, 'metadata' => []];
        $this->refundWebhook('re_dash', 'refund.created')->assertOk();

        $dashboard = SubscriptionRefund::where('reference', 're_dash')->sole();
        $this->assertSame([SubscriptionRefund::SUCCEEDED, '5.00', null, $payment->id], [$dashboard->status, $dashboard->amount, $dashboard->refunded_by, $dashboard->subscription_payment_id]);
        $this->assertSame(1, $this->refundNotices());

        // A refund of a payment izSuite does not know is left alone.
        $this->stripeRefunds['re_other'] = ['id' => 're_other', 'amount' => 100, 'currency' => 'usd', 'status' => 'succeeded', 'payment_intent' => 'pi_someone_else', 'metadata' => []];
        $this->refundWebhook('re_other', 'refund.created')->assertOk();
        $this->assertSame(2, SubscriptionRefund::count());
    }

    public function test_each_role_sees_or_refunds_only_its_part(): void
    {
        $invoice = $this->subscribeByTransfer();
        $payment = SubscriptionPayment::sole();
        $role    = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);

        $readOnly = $role('read-only-administrator');
        $this->actingAs($readOnly)->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.refunds.index'))->assertOk();
        $this->get(route('admin.subscription-invoices.show', $invoice->id))->assertOk()->assertDontSee('refund-payment-'.$payment->id);
        $this->refund($payment, ['amount' => '5'], $readOnly)->assertForbidden();

        $this->actingAs($role('support-administrator'))->get(route('admin.payments.index'))->assertForbidden();

        $this->refund($payment, ['amount' => '5'], $role('billing-administrator'))->assertOk();
    }
}
