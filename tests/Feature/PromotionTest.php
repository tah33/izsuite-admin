<?php

namespace Tests\Feature;

use App\Mail\SubscriptionBillingMail;
use App\Models\Admin\Currency;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\Promotion;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionTax;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PricesPlans;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase C3b (section 15.17): promotion codes - added in Admin >
 * Promotions, typed by a business when it subscribes - take a percent or a
 * fixed amount off the first invoice, before credits and tax; renewals are
 * at the full price. A code holds only for the plans, cycles, countries and
 * days it names, up to its uses, and for new customers if so set.
 */
class PromotionTest extends TestCase
{
    use PricesPlans;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        Http::fake(['*' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));

        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_active' => true]);
        $this->priced('standard', '19.50', '195.00');
        $this->priced('premium', '49.00', '490.00');
        // A 10% tax, to show the discount comes off before it.
        SubscriptionTax::create(['country' => 'BD', 'name' => 'VAT', 'rate' => '10', 'starts_on' => '2026-11-01', 'reverse_charge' => false]);

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        [$this->owner, $this->business] = $this->customer('Karim Stores');
    }

    /** @return array{0: User, 1: Workspace} a business's owner, signed in, with billing details in Bangladesh */
    private function customer(string $name): array
    {
        $this->app['auth']->forgetGuards();
        $owner    = $this->signIn();
        $business = $this->businessOf($owner);
        $business->update(['name' => $name]);
        $this->putJson('/api/v1/subscription/billing-details', ['name' => "{$name} Ltd", 'email' => 'accounts@example.com', 'address' => 'Dhaka', 'country' => 'BD'], $this->pick($business))->assertOk();

        return [$owner, $business];
    }

    /** Signed in as $user: an admin to the admin pages, else a business's user to the API. */
    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $user->isAdmin() ? $this->actingAs($user) : Sanctum::actingAs($user);

        return $this;
    }

    private function promotion(array $data): Promotion
    {
        return Promotion::create([...['name' => 'Test', 'discount_type' => Promotion::PERCENT, 'discount_value' => 10, 'per_business' => 1, 'status' => Promotion::ACTIVE], ...$data]);
    }

    private function quote(string $code, ?Workspace $business = null, string $plan = 'standard', string $cycle = 'monthly'): TestResponse
    {
        return $this->getJson("/api/v1/subscription/quote?package={$plan}&cycle={$cycle}&code={$code}", $this->pick($business ?? $this->business));
    }

    private function subscribe(string $code, ?Workspace $business = null, string $plan = 'standard'): TestResponse
    {
        return $this->postJson('/api/v1/subscription', ['package' => $plan, 'cycle' => 'monthly', 'code' => $code], $this->pick($business ?? $this->business));
    }

    private function refused(TestResponse $response, string $why): void
    {
        $response->assertUnprocessable()->assertJsonPath('errors.code.0', $why);
    }

    public function test_a_code_takes_its_discount_off_the_first_invoice_before_tax_and_renewals_are_at_the_full_price(): void
    {
        $form = ['name' => 'Spring launch', 'code' => 'spring20', 'discount_type' => 'percent', 'discount_value' => '20', 'packages' => ['standard'], 'per_business' => '1', 'new_customers_only' => '0', 'status' => 'active'];

        $this->as($this->admin)->post(route('admin.promotions.store'), $form)->assertRedirect(route('admin.promotions.index'));
        $promotion = Promotion::sole();
        $this->assertSame(['SPRING20', ['standard'], null, null, null], [$promotion->code, $promotion->packages, $promotion->cycles, $promotion->countries, $promotion->currency]);
        $this->get(route('admin.promotions.index'))->assertOk()->assertSeeInOrder(['Spring launch', 'SPRING20', '20% off', 'Standard', 'Active']);

        // 20% of 19.50 is 3.90; the 10% tax is on the 15.60 left. A renewal is 19.50 and its tax.
        $this->as($this->owner)->quote('spring20')->assertOk()->assertJsonPath('data', [
            'currency' => 'USD', 'amount' => '19.50', 'discount' => '3.90', 'promotion' => 'SPRING20', 'adjustment' => '0.00',
            'tax'      => ['name' => 'VAT', 'rate' => '10.000', 'amount' => '1.56', 'note' => null], 'total' => '17.16', 'renews_at' => '21.45',
        ]);

        $page    = $this->subscribe('Spring20')->assertCreated()->json('data.open_invoice');
        $this->assertSame(['19.50', '3.90', 'SPRING20', '17.16'], [$page['amount'], $page['discount'], $page['promotion_code'], $page['total']]);

        $invoice = SubscriptionInvoice::findOrFail($page['id']);
        $this->assertSame([$promotion->id, '3.90', '1.56'], [$invoice->promotion_id, (string) $invoice->discount, (string) $invoice->tax]);
        $this->assertStringContainsString('Discount (SPRING20)', view('billing.invoice', ['invoice' => $invoice, 'receipt' => false, 'logo' => null])->render());
        $this->assertTrue(ActivityLog::where('description', 'like', '%issued with code SPRING20')->exists());

        $this->as($this->admin)->get(route('admin.subscription-invoices.show', $invoice->id))->assertOk()->assertSeeInOrder(['Subtotal', 'USD 19.50', 'Discount', '− USD 3.90 (SPRING20)', 'Total', 'USD 17.16']);
        $this->post(route('admin.subscription-invoices.confirm', $invoice->id))->assertRedirect();
        $this->assertSame(1, $this->get(route('admin.promotions.index'))->viewData('promotions')->first()->uses);

        // The plan's price is kept whole for its renewals.
        $subscription = $this->business->subscription()->firstOrFail();
        $this->assertSame([WorkspaceSubscription::ACTIVE, '19.50'], [$subscription->status, (string) $subscription->price]);

        $this->travelTo(Carbon::parse('2026-12-01 10:00:00', 'UTC'));
        $this->artisan('subscriptions:advance')->assertSuccessful();
        $renewal = SubscriptionInvoice::where('kind', SubscriptionInvoice::RENEWAL)->sole();
        $this->assertSame(['19.50', '0.00', null, '1.95', '21.45'], [(string) $renewal->amount, (string) $renewal->discount, $renewal->promotion_code, (string) $renewal->tax, (string) $renewal->total]);
    }

    public function test_a_code_holds_only_for_the_plans_cycles_countries_currency_and_days_it_names(): void
    {
        $this->promotion(['code' => 'PAUSED', 'status' => Promotion::PAUSED]);
        $this->promotion(['code' => 'LATER', 'starts_on' => '2026-12-01']);
        $this->promotion(['code' => 'OVER', 'ends_on' => '2026-11-08']);
        $this->promotion(['code' => 'PREMIUM', 'packages' => ['premium']]);
        $this->promotion(['code' => 'YEARLY', 'cycles' => ['yearly']]);
        $this->promotion(['code' => 'GERMANY', 'countries' => ['DE']]);
        $this->promotion(['code' => 'EURO5', 'discount_type' => Promotion::FIXED, 'discount_value' => 5, 'currency' => 'EUR']);

        $this->refused($this->quote('NOPE'), 'There is no promotion with this code.');
        $this->refused($this->quote('paused'), 'This code is not in use right now.');
        $this->refused($this->quote('LATER'), 'This code can be used from 1 Dec 2026.');
        $this->refused($this->quote('OVER'), 'This code has ended.');
        $this->refused($this->quote('PREMIUM'), 'This code is not for Standard.');
        $this->refused($this->quote('YEARLY'), 'This code is for yearly plans only.');
        $this->refused($this->quote('GERMANY'), 'This code is not for businesses billed in Bangladesh.');
        $this->refused($this->quote('EURO5'), 'This code is for prices in EUR.');
        // Refused on subscribing too, before anything changes.
        $this->refused($this->subscribe('OVER'), 'This code has ended.');
        $this->assertSame(0, SubscriptionInvoice::count());

        // Where it holds: the plan, cycle and country named, its first and last days included.
        $this->promotion(['code' => 'FITS', 'packages' => ['premium'], 'cycles' => ['yearly'], 'countries' => ['BD'], 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09']);
        $this->quote('FITS', plan: 'premium', cycle: 'yearly')->assertOk()->assertJsonPath('data.discount', '49.00');

        // A fixed amount in the price's currency, never more than the price; a percent to the nearest cent, half up.
        $this->promotion(['code' => 'USD5', 'discount_type' => Promotion::FIXED, 'discount_value' => 5, 'currency' => 'USD']);
        $this->promotion(['code' => 'USD50', 'discount_type' => Promotion::FIXED, 'discount_value' => 50, 'currency' => 'USD']);
        $this->promotion(['code' => 'EIGHTH', 'discount_value' => '12.50']);
        $this->quote('USD5')->assertJsonPath('data.discount', '5.00')->assertJsonPath('data.total', '15.95');
        $this->quote('USD50')->assertJsonPath('data.discount', '19.50')->assertJsonPath('data.tax.amount', '0.00')->assertJsonPath('data.total', '0.00');
        $this->quote('EIGHTH')->assertJsonPath('data.discount', '2.44');
    }

    public function test_uses_are_counted_until_an_invoice_is_voided_and_new_customer_codes_skip_businesses_that_paid(): void
    {
        [$other, $otherBusiness] = $this->customer('Rahim Traders');
        $this->promotion(['code' => 'ONLYONE', 'max_redemptions' => 1]);

        $this->as($this->owner)->subscribe('ONLYONE')->assertCreated();
        $this->as($other)->refused($this->quote('ONLYONE', $otherBusiness), 'This code has been used up.');

        // Withdrawn, its invoice is void: the use is given back.
        $this->as($this->owner)->postJson('/api/v1/subscription/withdraw', [], $this->pick($this->business))->assertOk();
        $this->as($other)->quote('ONLYONE', $otherBusiness)->assertOk()->assertJsonPath('data.promotion', 'ONLYONE');

        // Once a business, however many in all.
        $this->promotion(['code' => 'ONCE']);
        $id = $this->as($this->owner)->subscribe('ONCE')->assertCreated()->json('data.open_invoice.id');
        $this->refused($this->quote('ONCE'), 'Your business has used this code already.');

        // For businesses that never paid izSuite.
        $this->promotion(['code' => 'NEWHERE', 'new_customers_only' => true]);
        $this->as($this->admin)->post(route('admin.subscription-invoices.confirm', $id))->assertRedirect();
        $this->as($this->owner)->refused($this->quote('NEWHERE'), 'This code is for new customers.');
        $this->as($other)->quote('NEWHERE', $otherBusiness)->assertOk();
    }

    public function test_a_code_taking_the_whole_price_off_settles_the_first_invoice_at_once(): void
    {
        $this->promotion(['code' => 'FREEMONTH', 'discount_value' => 100]);

        $this->subscribe('FREEMONTH')->assertCreated()
            ->assertJsonPath('message', 'Invoice IZS-INV-00001 is issued and settled by code FREEMONTH, with nothing to pay. Standard has started.')
            ->assertJsonPath('data.status', 'active');

        $invoice = SubscriptionInvoice::sole();
        $this->assertSame([SubscriptionInvoice::PAID, '19.50', '0.00', '0.00', 'Promotion code'], [$invoice->status, (string) $invoice->discount, (string) $invoice->tax, (string) $invoice->total, $invoice->paid_with]);
        $this->assertSame(0, SubscriptionPayment::count());
        Mail::assertQueued(SubscriptionBillingMail::class, fn (SubscriptionBillingMail $mail) => $mail->invoice->is($invoice) && $mail->receipt);
        $this->assertStringContainsString('Nothing was due: code FREEMONTH took the whole price off', view('billing.invoice', ['invoice' => $invoice, 'receipt' => true, 'logo' => null])->render());

        // A trial's invoice for the plan after it, likewise.
        Package::where('key', 'premium')->update(['trial_days' => 14]);
        [, $other] = $this->customer('Rahim Traders');
        $this->subscribe('FREEMONTH', $other, 'premium')->assertCreated()
            ->assertJsonPath('message', 'Your 14-day Premium trial has started, and invoice IZS-INV-00002 for the plan after it is settled by code FREEMONTH, with nothing to pay.');
        $this->assertSame(SubscriptionInvoice::PAID, SubscriptionInvoice::where('number', 'IZS-INV-00002')->value('status'));
    }

    public function test_the_admin_form_checks_its_fields_a_used_code_stays_and_the_roles_hold_the_pages(): void
    {
        $form = ['name' => 'Welcome', 'code' => 'WELCOME5', 'discount_type' => 'fixed', 'discount_value' => '5', 'currency' => 'usd', 'countries' => ['BD'], 'per_business' => '1', 'status' => 'active'];
        $this->as($this->admin);

        $this->post(route('admin.promotions.store'), [...$form, 'currency' => ''])->assertSessionHasErrors(['currency' => 'A fixed amount needs its currency: it comes off invoices in it only.']);
        $this->post(route('admin.promotions.store'), [...$form, 'discount_type' => 'percent', 'discount_value' => '120'])->assertSessionHasErrors(['discount_value' => 'A percent is 100 at most.']);
        $this->post(route('admin.promotions.store'), [...$form, 'code' => 'W5'])->assertSessionHasErrors(['code' => 'Use 3 to 40 letters, numbers, - or _.']);
        $this->post(route('admin.promotions.store'), [...$form, 'starts_on' => '2026-12-01', 'ends_on' => '2026-11-30'])->assertSessionHasErrors(['ends_on' => 'The last day cannot come before the first.']);
        $this->post(route('admin.promotions.store'), $form)->assertRedirect(route('admin.promotions.index'));
        $this->post(route('admin.promotions.store'), [...$form, 'code' => 'welcome5'])->assertSessionHasErrors(['code' => 'Another promotion has this code.']);

        $welcome = Promotion::sole();
        $this->assertSame(['USD', '5.00', ['BD'], 'USD 5.00 off'], [$welcome->currency, (string) $welcome->discount_value, $welcome->countries, $welcome->describe()]);

        // A percent keeps no currency; paused, it is listed so.
        $this->get(route('admin.promotions.edit', $welcome->id))->assertOk()->assertSee('Taken 0 times');
        $this->put(route('admin.promotions.update', $welcome->id), [...$form, 'discount_type' => 'percent', 'discount_value' => '15', 'status' => 'paused'])->assertRedirect(route('admin.promotions.index'));
        $this->assertSame([null, Promotion::PAUSED, '15% off'], [$welcome->fresh()->currency, $welcome->fresh()->status, $welcome->fresh()->describe()]);
        $this->get(route('admin.promotions.index'))->assertSeeInOrder(['WELCOME5', '15% off', 'Paused']);

        // Never taken, it can go; taken, it stays - its invoices name it.
        $this->delete(route('admin.promotions.destroy', $welcome->id))->assertRedirect();
        $this->assertNull($welcome->fresh());

        $taken = $this->promotion(['code' => 'TAKEN']);
        $this->as($this->owner)->subscribe('TAKEN')->assertCreated();
        $this->as($this->admin)->delete(route('admin.promotions.destroy', $taken->id))->assertSessionHasErrors(['promotion' => 'TAKEN was taken 1 time: pause it or end it instead, as its invoices name it.']);
        $this->assertNotNull($taken->fresh());

        $role = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);
        $readOnly = $role('read-only-administrator');

        $this->as($readOnly)->get(route('admin.promotions.index'))->assertOk()->assertDontSee('Add Promotion');
        $this->get(route('admin.promotions.create'))->assertForbidden();
        $this->as($role('billing-administrator'))->get(route('admin.promotions.create'))->assertOk();
        $this->as($role('support-administrator'))->get(route('admin.promotions.index'))->assertForbidden();
    }
}
