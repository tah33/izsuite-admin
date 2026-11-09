<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\WorkspaceCard;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Paying izSuite's invoices by card, through Stripe (Doc 13 section 15, Doc
 * 15 section 15) - Doc 13 Phase D2: the keys kept encrypted and shown
 * masked, Stripe's own page to pay or to save a card, a finished checkout
 * settled once - by its webhook or the business coming back - and the saved
 * card replaced or removed. Stripe's API is faked.
 */
class SubscriptionCardTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SIGNING = 'whsec_test_signing';

    private User $owner;

    private Workspace $business;

    private User $admin;

    /** @var array<string, array> Stripe's checkouts, by id, as the fake answers for them. */
    private array $sessions = [];

    private bool $stripeDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));
        $this->fakeStripe();

        Package::where('key', 'standard')->update(['price_monthly' => '19.50', 'price_yearly' => '195.00']);

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
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

            if ($this->stripeDown) {
                return Http::response(['error' => ['message' => 'Invalid API Key provided.']], 401);
            }

            if ($path === '/v1/customers') {
                return Http::response(['id' => 'cus_uddin']);
            }

            if ($path === '/v1/checkout/sessions') {
                $id                  = 'cs_test_'.(count($this->sessions) + 1);
                $this->sessions[$id] = ['id' => $id, 'mode' => $request['mode'], 'status' => 'open', 'customer' => 'cus_uddin', 'livemode' => false, 'metadata' => $request['metadata']];

                return Http::response(['id' => $id, 'url' => "https://checkout.stripe.com/c/pay/{$id}"]);
            }

            if (preg_match('#^/v1/checkout/sessions/(cs_\w+)$#', $path, $match)) {
                return isset($this->sessions[$match[1]]) ? Http::response($this->sessions[$match[1]]) : Http::response(['error' => ['message' => 'No such checkout.session']], 404);
            }

            if (preg_match('#^/v1/payment_methods/\w+/detach$#', $path)) {
                return Http::response(['id' => 'pm']);
            }

            return Http::response(['error' => ['message' => "Not faked: {$path}"]], 500);
        });
    }

    private function stripeOn(): void
    {
        PaymentMethod::where('slug', 'stripe')->firstOrFail()->update([
            'is_active'   => true,
            'is_sandbox'  => true,
            'credentials' => ['test' => ['publishable_key' => 'pk_test_123', 'secret_key' => 'sk_test_123', 'webhook_secret' => self::SIGNING]],
        ]);
    }

    /** The business, on Stripe's page, pays checkout $id with a card. */
    private function pays(string $id, string $card = 'pm_visa', string $brand = 'visa', string $last4 = '4242'): void
    {
        $invoice             = SubscriptionInvoice::findOrFail($this->sessions[$id]['metadata']['invoice_id']);
        $this->sessions[$id] = [...$this->sessions[$id], 'status' => 'complete', 'payment_status' => 'paid', 'currency' => 'usd', 'payment_intent' => [
            'id' => "pi_{$id}", 'amount_received' => (int) bcmul((string) $invoice->total, '100'), 'payment_method' => self::card($card, $brand, $last4),
        ]];
    }

    /** The business, on Stripe's page, saves a card in checkout $id. */
    private function saves(string $id, string $card, string $brand, string $last4): void
    {
        $this->sessions[$id] = [...$this->sessions[$id], 'status' => 'complete', 'setup_intent' => ['id' => "seti_{$id}", 'payment_method' => self::card($card, $brand, $last4)]];
    }

    private static function card(string $id, string $brand, string $last4): array
    {
        return ['id' => $id, 'card' => ['brand' => $brand, 'last4' => $last4, 'exp_month' => 8, 'exp_year' => 2030]];
    }

    /** Stripe's webhook for checkout $id, signed with $secret. */
    private function webhook(string $id, string $type = 'checkout.session.completed', string $secret = self::SIGNING)
    {
        $payload = json_encode(['id' => "evt_{$id}", 'type' => $type, 'data' => ['object' => ['id' => $id, 'object' => 'checkout.session']]]);
        $time    = now()->timestamp;

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", $secret), 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], $payload);
    }

    private function openInvoice(): int
    {
        return $this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'])->assertCreated()->json('data.open_invoice.id');
    }

    public function test_the_admin_keeps_stripes_keys_encrypted_and_shown_masked(): void
    {
        $stripe = PaymentMethod::where('slug', 'stripe')->firstOrFail();
        $form   = ['is_active' => '1', 'is_sandbox' => '1', 'credentials' => ['test' => ['publishable_key' => 'pk_test_abc', 'secret_key' => 'sk_test_secret9876', 'webhook_secret' => 'whsec_sign5555']]];

        $this->actingAs($this->admin)->put(route('admin.payment-methods.update', $stripe->id), $form)->assertRedirect();

        $this->assertStringNotContainsString('sk_test_secret9876', (string) DB::table('payment_methods')->where('slug', 'stripe')->value('credentials'));
        $this->assertSame('sk_test_secret9876', $stripe->fresh()->keys()['secret_key']);

        $this->actingAs($this->admin)->get(route('admin.payment-methods.edit', $stripe->id))->assertOk()
            ->assertSee('sk_test_…9876')->assertSee('whsec_…5555')->assertDontSee('sk_test_secret9876')->assertSee(route('api.webhooks.stripe'));

        // A key left blank stays; a key of the wrong mode is refused; live needs live keys.
        $this->actingAs($this->admin)->put(route('admin.payment-methods.update', $stripe->id), ['is_active' => '1', 'is_sandbox' => '1', 'credentials' => ['test' => ['secret_key' => '']]])->assertRedirect();
        $this->assertSame('sk_test_secret9876', $stripe->fresh()->keys()['secret_key']);
        $this->actingAs($this->admin)->put(route('admin.payment-methods.update', $stripe->id), ['is_sandbox' => '0', 'credentials' => ['live' => ['secret_key' => 'sk_test_oops']]])->assertSessionHasErrors('credentials.live.secret_key');
        $this->actingAs($this->admin)->put(route('admin.payment-methods.update', $stripe->id), ['is_active' => '1', 'is_sandbox' => '0'])->assertSessionHasErrors('credentials');

        // Only the gateways built show, and open.
        $this->actingAs($this->admin)->get(route('admin.payment-methods.index', ['tab' => 'online']))->assertOk()->assertSee('Stripe')->assertDontSee('Razorpay');
        $this->actingAs($this->admin)->get(route('admin.payment-methods.edit', PaymentMethod::where('slug', 'razorpay')->value('id')))->assertNotFound();
    }

    public function test_an_invoice_paid_on_stripes_page_is_paid_once(): void
    {
        $this->stripeOn();
        $id = $this->openInvoice();

        $this->getJson('/api/v1/subscription')->assertJsonPath('data.card_payments', true)->assertJsonPath('data.card', null);
        $this->postJson("/api/v1/subscription/invoices/{$id}/card-checkout")->assertOk()->assertJsonPath('data.url', 'https://checkout.stripe.com/c/pay/cs_test_1');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['mode'] === 'payment' && $request['customer'] === 'cus_uddin'
            && $request['line_items'][0]['price_data'] === ['currency' => 'usd', 'unit_amount' => 1950, 'product_data' => ['name' => 'izSuite Standard plan, monthly (IZS-INV-00001)']]
            && $request['payment_intent_data']['setup_future_usage'] === 'off_session' && $request['metadata']['invoice_id'] === $id
            && str_ends_with($request['success_url'], '/settings?tab=subscription&card_checkout={CHECKOUT_SESSION_ID}'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/customers' && $request['email'] === 'accounts@uddin.example');

        // Paid on Stripe's page: the webhook settles it, once however often it comes.
        $this->pays('cs_test_1');
        $this->webhook('cs_test_1')->assertOk();
        $this->webhook('cs_test_1')->assertOk();

        $invoice = SubscriptionInvoice::findOrFail($id);

        $this->assertSame(['paid', 'IZS-RCT-00001', 'Visa ending 4242', 'pi_cs_test_1', null], [$invoice->status, $invoice->receipt_number, $invoice->paid_with, $invoice->payment_reference, $invoice->confirmed_by]);
        $this->assertSame(1, SubscriptionPayment::count());
        $this->assertSame(['standard', 'active'], [$this->business->fresh()->package->key, $this->business->subscription()->value('status')]);
        $this->assertTrue(ActivityLog::where('description', 'Paid invoice IZS-INV-00001 by card (Visa ending 4242)')->exists());
        $this->assertStringContainsString('by card, Visa ending 4242, reference pi_cs_test_1', view('billing.invoice', ['invoice' => $invoice, 'receipt' => true, 'logo' => null])->render());

        // Coming back from Stripe after the webhook: nothing more to do.
        $this->postJson('/api/v1/subscription/card-checkout', ['session_id' => 'cs_test_1'])->assertOk()
            ->assertJsonPath('message', 'Invoice IZS-INV-00001 is paid. Thank you.')
            ->assertJsonPath('data.card', ['brand' => 'Visa', 'last4' => '4242', 'exp_month' => 8, 'exp_year' => 2030]);

        // Not Stripe's signature, or another event: nothing done.
        $this->webhook('cs_test_1', secret: 'whsec_forged')->assertStatus(400);
        $this->webhook('cs_test_1', 'payment_intent.created')->assertOk();
        $this->actingAs($this->admin)->get(route('admin.subscription-invoices.index'))->assertSee('Visa ending 4242')->assertSee('pi_cs_test_1');
    }

    public function test_coming_back_settles_a_payment_stripe_has_not_reported(): void
    {
        $this->stripeOn();
        $id = $this->openInvoice();
        $this->postJson("/api/v1/subscription/invoices/{$id}/card-checkout")->assertOk();

        // Not paid yet: nothing to settle.
        $this->postJson('/api/v1/subscription/card-checkout', ['session_id' => 'cs_test_1'])->assertStatus(409);

        $this->pays('cs_test_1');
        $this->postJson('/api/v1/subscription/card-checkout', ['session_id' => 'cs_test_1'])->assertOk()
            ->assertJsonPath('message', 'Thank you: invoice IZS-INV-00001 is paid by Visa ending 4242. Receipt IZS-RCT-00001 is on its way by e-mail.')
            ->assertJsonPath('data.status', 'active');

        $this->webhook('cs_test_1')->assertOk();
        $this->assertSame(1, SubscriptionPayment::count());
        $this->postJson('/api/v1/subscription/card-checkout', ['session_id' => 'not-a-checkout'])->assertJsonValidationErrors('session_id');
    }

    public function test_a_payment_that_cannot_pay_its_invoice_is_kept_for_a_refund(): void
    {
        $this->stripeOn();
        $id = $this->openInvoice();
        $this->postJson("/api/v1/subscription/invoices/{$id}/card-checkout")->assertOk();
        $this->postJson('/api/v1/subscription/withdraw')->assertOk();

        $this->pays('cs_test_1');
        $this->webhook('cs_test_1')->assertOk();

        $this->assertSame('void', SubscriptionInvoice::findOrFail($id)->status);
        $this->assertSame('Not applied: Invoice IZS-INV-00001 was cancelled before it arrived. Refund it in Stripe.', SubscriptionPayment::sole()->note);
        $this->actingAs($this->admin)->get(route('admin.subscription-invoices.index'))->assertSee('was cancelled before it arrived. Refund it in Stripe.');
    }

    public function test_the_saved_card_is_added_replaced_and_removed(): void
    {
        $this->stripeOn();

        $this->postJson('/api/v1/subscription/card')->assertOk()->assertJsonPath('data.url', 'https://checkout.stripe.com/c/pay/cs_test_1');
        $this->saves('cs_test_1', 'pm_visa', 'visa', '4242');
        $this->postJson('/api/v1/subscription/card-checkout', ['session_id' => 'cs_test_1'])->assertOk()
            ->assertJsonPath('message', 'Visa ending 4242 is saved for your subscription payments.')
            ->assertJsonPath('data.card.last4', '4242');

        // Another in its place: the one before comes off Stripe.
        $this->postJson('/api/v1/subscription/card')->assertOk();
        $this->saves('cs_test_2', 'pm_master', 'mastercard', '4444');
        $this->webhook('cs_test_2')->assertOk();

        $this->getJson('/api/v1/subscription')->assertJsonPath('data.card', ['brand' => 'Mastercard', 'last4' => '4444', 'exp_month' => 8, 'exp_year' => 2030]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/payment_methods/pm_visa/detach');

        $this->deleteJson('/api/v1/subscription/card')->assertOk()
            ->assertJsonPath('message', "Mastercard ending 4444 is removed. Pay your invoices by card on Stripe's page, or by bank transfer.")
            ->assertJsonPath('data.card', null);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/payment_methods/pm_master/detach');
        $this->deleteJson('/api/v1/subscription/card')->assertStatus(409);

        foreach (['Saved Visa ending 4242 for subscription payments', 'Replaced Visa ending 4242 with Mastercard ending 4444 for subscription payments', 'Removed Mastercard ending 4444 from subscription payments'] as $entry) {
            $this->assertTrue(ActivityLog::where('description', $entry)->exists(), $entry);
        }

        // The customer stays for the next card.
        $this->assertSame('cus_uddin', WorkspaceCard::where('workspace_id', $this->business->id)->value('customer_id'));
    }

    public function test_cards_are_offered_only_while_stripe_is_on_and_answering(): void
    {
        $id = $this->openInvoice();

        $this->getJson('/api/v1/subscription')->assertJsonPath('data.card_payments', false);
        $this->postJson("/api/v1/subscription/invoices/{$id}/card-checkout")->assertStatus(409)->assertJsonPath('message', 'Card payments are not available right now. Pay by bank transfer instead.');
        $this->postJson('/api/v1/subscription/card')->assertStatus(409);
        $this->webhook('cs_test_1')->assertStatus(400);

        $this->stripeOn();
        $this->stripeDown = true;
        $this->postJson("/api/v1/subscription/invoices/{$id}/card-checkout")->assertStatus(502)
            ->assertJsonPath('message', 'Card payments are not available right now. Please try again, or pay by bank transfer.');

        // Another business's invoice, or one not to be paid: no.
        $this->stripeDown = false;
        $this->postJson('/api/v1/subscription/invoices/999/card-checkout')->assertNotFound();
        $this->postJson('/api/v1/subscription/withdraw')->assertOk();
        $this->postJson("/api/v1/subscription/invoices/{$id}/card-checkout")->assertStatus(409);
    }
}
