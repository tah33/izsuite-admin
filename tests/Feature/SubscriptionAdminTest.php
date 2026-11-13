<?php

namespace Tests\Feature;

use App\Mail\AccountNoticeMail;
use App\Mail\SubscriptionBillingMail;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceCard;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\Shared\UserNotification;
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
use Tests\TestCase;

/**
 * Doc 15 Phase C1: Admin > Subscriptions (section 15.13). Every business's
 * subscription where it stands - Past Due as the app says it - and izSuite's
 * changes to it through Doc 13's own billing: a plan change, a trial or grace
 * extended, a cancellation scheduled and undone, an invoice resent, a refused
 * card tried again. Each change has a reason the audit log keeps, and the
 * owner is told. Stripe's API is faked.
 */
class SubscriptionAdminTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    private User $admin;

    /** @var list<string> what Stripe answers the next charges: succeeded or declined */
    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));
        $this->fakeStripe();

        Package::where('key', 'standard')->update(['price_monthly' => '19.50', 'price_yearly' => '195.00', 'grace_days' => 5]);
        Package::where('key', 'premium')->update(['price_monthly' => '49.00', 'price_yearly' => '490.00', 'trial_days' => 14]);
        PaymentMethod::where('slug', 'stripe')->firstOrFail()->update([
            'is_active'   => true, 'is_sandbox' => true,
            'credentials' => ['test' => ['publishable_key' => 'pk_test_123', 'secret_key' => 'sk_test_123', 'webhook_secret' => 'whsec_test']],
        ]);

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
        $this->owner    = $this->signIn(User::factory()->create(['first_name' => 'Nadia']));
        $this->business = $this->businessOf($this->owner);
        $this->business->update(['name' => 'Karim Stores']);
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Karim Stores Ltd', 'email' => 'accounts@karim.example', 'address' => 'Dhaka', 'country' => 'BD'])->assertOk();
    }

    private function fakeStripe(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/v1/payment_intents') {
                $answer = array_shift($this->answers) ?? 'succeeded';
                $intent = ['id' => 'pi_'.uniqid(), 'amount_received' => $answer === 'succeeded' ? $request['amount'] : 0, 'currency' => $request['currency'], 'customer' => $request['customer'],
                    'livemode' => false, 'metadata' => $request['metadata'], 'payment_method' => ['id' => $request['payment_method'], 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 8, 'exp_year' => 2030]]];

                return $answer === 'declined'
                    ? Http::response(['error' => ['type' => 'card_error', 'code' => 'card_declined', 'message' => 'Your card has insufficient funds.', 'payment_intent' => [...$intent, 'status' => 'requires_payment_method']]], 402)
                    : Http::response([...$intent, 'status' => 'succeeded', 'payment_method' => $request['payment_method']]);
            }

            return Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf']);
        });
    }

    /** $plan, $cycle, paid by transfer on 9 Nov - monthly, its period runs to 8 Dec. */
    private function subscribe(string $plan = 'standard', string $cycle = 'monthly'): WorkspaceSubscription
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/v1/subscription', ['package' => $plan, 'cycle' => $cycle], $this->pick($this->business))->assertCreated()->json('data.open_invoice.id');

        if ($plan === 'standard') {
            $this->actingAs($this->admin)->post(route('admin.subscription-invoices.confirm', $id))->assertRedirect();
        }

        return $this->business->subscription()->firstOrFail();
    }

    private function on(string $day): void
    {
        $this->travelTo(Carbon::parse("{$day} 10:00:00", 'UTC'));
        $this->artisan('subscriptions:advance')->assertSuccessful();
    }

    private function asAdmin(?User $admin = null): static
    {
        return $this->actingAs($admin ?? $this->admin);
    }

    public function test_the_list_shows_each_subscription_where_it_stands(): void
    {
        $subscription = $this->subscribe();

        $other = User::factory()->create();
        $trial = $this->businessOf($other);
        $trial->update(['name' => 'Uddin Traders']);
        $trial->subscription()->create(['package_id' => Package::where('key', 'premium')->value('id'), 'cycle' => 'monthly', 'status' => 'trialing', 'trial_starts_on' => '2026-11-09', 'trial_ends_on' => '2026-11-22']);

        $this->asAdmin()->get(route('admin.subscriptions.index'))->assertOk()->assertSeeInOrder(['Uddin Traders', 'Trial', 'Karim Stores', 'Active']);
        $this->get(route('admin.subscriptions.index', ['status' => 'trialing']))->assertSee('Uddin Traders')->assertDontSee('Karim Stores');
        $this->get(route('admin.subscriptions.index', ['package' => 'standard']))->assertSee('Karim Stores')->assertDontSee('Uddin Traders');
        $this->get(route('admin.subscriptions.index', ['search' => $this->business->businessId()]))->assertSee('Karim Stores')->assertDontSee('Uddin Traders');
        $this->get(route('admin.subscriptions.index', ['provider' => 'card']))->assertDontSee('Karim Stores');

        $this->get(route('admin.subscriptions.show', $subscription->id))->assertOk()
            ->assertSee('Standard')->assertSee('Bank transfer')->assertSee(SubscriptionInvoice::latest('id')->value('number'));
    }

    public function test_a_trial_is_extended_and_its_invoice_issued_again(): void
    {
        $subscription = $this->subscribe('premium');
        $first        = SubscriptionInvoice::sole();
        $url          = route('admin.subscriptions.trial', $subscription->id);

        $this->asAdmin()->postJson($url, ['days' => 7])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->postJson($url, ['days' => 7, 'reason' => 'The owner was ill for a week'])->assertOk()->assertJsonPath('message', 'The trial now ends on 29 Nov 2026.');

        $this->assertSame('2026-11-29', $subscription->fresh()->trial_ends_on->toDateString());
        $this->assertSame(SubscriptionInvoice::VOID, $first->fresh()->status);
        $again = SubscriptionInvoice::where('status', SubscriptionInvoice::OPEN)->sole();
        $this->assertSame(['2026-11-30', '2026-11-29'], [$again->period_starts_on->toDateString(), $again->due_on->toDateString()]);

        $this->assertSame('The owner was ill for a week', AdminAuditLog::where('description', 'like', 'Extended the Premium trial%')->sole()->reason);
        $this->assertSame('izsuite.subscription', UserNotification::where('user_id', $this->owner->id)->where('trigger', 'like', 'izsuite.%')->sole()->trigger);
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->hasTo($this->owner->email) && $mail->title === 'Your trial now ends on 29 Nov 2026');
    }

    public function test_a_grace_period_is_extended(): void
    {
        $subscription = $this->subscribe();
        $this->on('2026-12-09');
        $this->assertSame([WorkspaceSubscription::GRACE, '2026-12-13'], [$subscription->fresh()->status, $subscription->fresh()->grace_ends_on->toDateString()]);

        $this->asAdmin()->postJson(route('admin.subscriptions.grace', $subscription->id), ['days' => 3, 'reason' => 'The bank transfer is on its way'])->assertOk();

        $this->assertSame('2026-12-16', $subscription->fresh()->grace_ends_on->toDateString());
        $this->postJson(route('admin.subscriptions.trial', $subscription->id), ['days' => 3, 'reason' => 'Not on trial'])->assertStatus(422);
    }

    public function test_a_cancellation_and_a_move_down_are_scheduled_and_undone(): void
    {
        $subscription = $this->subscribe('standard', 'yearly');
        $reason       = ['reason' => 'The owner asked by phone'];

        $this->asAdmin()->postJson(route('admin.subscriptions.cancel', $subscription->id), $reason)->assertOk();
        $this->assertTrue($subscription->fresh()->cancels_at_period_end);
        $this->get(route('admin.subscriptions.index', ['status' => 'cancellation_scheduled']))->assertSee('Karim Stores');
        $this->postJson(route('admin.subscriptions.undo', $subscription->id), $reason)->assertOk();
        $this->assertFalse($subscription->fresh()->cancels_at_period_end);

        // Yearly to monthly is down: it waits for the paid year's end.
        $this->postJson(route('admin.subscriptions.plan', $subscription->id), [...$reason, 'package_id' => $subscription->package_id, 'cycle' => 'monthly'])
            ->assertOk()->assertJsonPath('message', 'Karim Stores moves to Standard, monthly, on 9 Nov 2027.');
        $this->assertSame('monthly', $subscription->fresh()->scheduled_cycle);
        $this->get(route('admin.subscriptions.index', ['status' => 'downgrade_scheduled']))->assertSee('Karim Stores');
        $this->postJson(route('admin.subscriptions.undo', $subscription->id), $reason)->assertOk();
        $this->assertNull($subscription->fresh()->scheduled_package_id);

        // Up: its invoice now, the plan once paid.
        $this->postJson(route('admin.subscriptions.plan', $subscription->id), [...$reason, 'package_id' => Package::where('key', 'premium')->value('id'), 'cycle' => 'yearly'])->assertOk();
        $this->assertSame(SubscriptionInvoice::UPGRADE, SubscriptionInvoice::where('status', SubscriptionInvoice::OPEN)->sole()->kind);
    }

    public function test_an_invoice_is_resent_and_a_refused_card_tried_again(): void
    {
        $subscription = $this->subscribe();
        WorkspaceCard::create(['workspace_id' => $this->business->id, 'provider' => 'stripe', 'livemode' => false, 'customer_id' => 'cus_karim', 'card_id' => 'pm_visa', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 8, 'exp_year' => 2030]);

        $this->asAdmin()->postJson(route('admin.subscriptions.retry', $subscription->id))->assertStatus(422)->assertJsonPath('message', 'There is no invoice waiting for payment.');

        $this->answers = ['declined'];
        $this->on('2026-12-01');
        $this->on('2026-12-08');
        // The filter lists every status before the table, so the badge must come after the business's name.
        $this->asAdmin()->get(route('admin.subscriptions.index', ['status' => 'past_due']))->assertSeeInOrder(['Karim Stores', 'Past Due']);
        $this->get(route('admin.subscriptions.index', ['status' => 'active']))->assertDontSee('Karim Stores');

        $before = count(Mail::queued(SubscriptionBillingMail::class));
        $this->postJson(route('admin.subscriptions.resend', $subscription->id))->assertOk();
        $this->assertCount($before + 1, Mail::queued(SubscriptionBillingMail::class));

        $this->postJson(route('admin.subscriptions.retry', $subscription->id))->assertOk()->assertJsonPath('message', fn (string $message) => str_contains($message, 'is paid'));
        $this->assertSame(SubscriptionInvoice::PAID, SubscriptionInvoice::where('kind', SubscriptionInvoice::RENEWAL)->sole()->status);
    }

    public function test_each_role_does_only_its_part(): void
    {
        $subscription = $this->subscribe();
        $role         = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);
        $reason       = ['reason' => 'Checking the subscription'];

        $support = $role('support-administrator');
        $this->asAdmin($support)->get(route('admin.subscriptions.show', $subscription->id))->assertOk()->assertDontSee('Schedule Cancellation');
        $this->postJson(route('admin.subscriptions.resend', $subscription->id))->assertOk();
        $this->postJson(route('admin.subscriptions.cancel', $subscription->id), $reason)->assertForbidden();

        $this->asAdmin($role('read-only-administrator'))->get(route('admin.subscriptions.index'))->assertOk();
        $this->postJson(route('admin.subscriptions.resend', $subscription->id))->assertForbidden();

        $this->asAdmin($role('billing-administrator'))->postJson(route('admin.subscriptions.cancel', $subscription->id), $reason)->assertOk();
    }
}
