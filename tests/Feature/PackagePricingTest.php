<?php

namespace Tests\Feature;

use App\Mail\SubscriptionNoticeMail;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\PackagePrice;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\Admin\Currency;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PricesPlans;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase C3a (sections 15.8 and 15.9): a plan's prices in several
 * currencies, from their days and kept as history. A business pays in its
 * own currency when the plan has a price in it, else in the plan's main
 * one; a subscriber keeps the price it pays until izSuite applies a new
 * one, from a day at least 30 days ahead it is told of now.
 */
class PackagePricingTest extends TestCase
{
    use PricesPlans;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $admin;

    private User $owner;

    private Workspace $business;

    private Package $standard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        Mail::fake();
        Http::fake(['*' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));

        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_active' => true]);
        Currency::create(['name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => '৳', 'exchange_rate' => 110, 'is_active' => true]);

        $this->standard = Package::where('key', 'standard')->sole();
        $this->priced('standard', '19.50', '195.00');
        $this->priced('premium', '49.00');

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
        [$this->owner, $this->business] = $this->newBusiness('Karim Stores');
    }

    /** @return array{0: User, 1: Workspace} a business set up as a new one is, with its billing details */
    private function newBusiness(string $name): array
    {
        $owner    = $this->signIn(User::factory()->create());
        $business = tap($this->businessOf($owner))->update(['name' => $name]);
        app(SetUpNewWorkspace::class)->handle($business, $owner, 'api');
        $this->putJson('/api/v1/subscription/billing-details', ['name' => "{$name} Ltd", 'email' => 'accounts@'.strtolower(str_replace(' ', '', $name)).'.example', 'address' => 'Dhaka', 'country' => 'BD'])->assertOk();

        return [$owner, $business];
    }

    private function asOwner(User $owner): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner);

        return $this;
    }

    private function asAdmin(?User $admin = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($admin ?? $this->admin);
    }

    /** Standard, monthly, for $business, paid by a transfer izSuite confirms. */
    private function subscribe(User $owner, Workspace $business): SubscriptionInvoice
    {
        $id = $this->asOwner($owner)->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'], $this->pick($business))->assertCreated()->json('data.open_invoice.id');
        $this->asAdmin()->post(route('admin.subscription-invoices.confirm', $id))->assertRedirect();

        return SubscriptionInvoice::findOrFail($id);
    }

    private function on(string $day): void
    {
        $this->travelTo(Carbon::parse("{$day} 10:00:00", 'UTC'));
        $this->artisan('subscriptions:advance')->assertSuccessful();
    }

    public function test_a_business_pays_in_its_own_currency_when_the_plan_has_a_price_in_it(): void
    {
        $this->priced('standard', '2000.00', '20000.00', 'BDT');
        $this->business->forceFill(['base_currency' => 'BDT'])->save();

        $offers = collect($this->asOwner($this->owner)->getJson('/api/v1/subscription', $this->pick($this->business))->json('data.packages'))->keyBy('key');
        $this->assertSame(['BDT', '2000.00', '20000.00'], [$offers['standard']['price_currency'], $offers['standard']['price_monthly'], $offers['standard']['price_yearly']]);
        // Premium has no taka price: its main currency.
        $this->assertSame(['USD', '49.00'], [$offers['premium']['price_currency'], $offers['premium']['price_monthly']]);
        $this->getJson('/api/v1/subscription/quote?package=standard&cycle=monthly', $this->pick($this->business))->assertJsonPath('data.currency', 'BDT')->assertJsonPath('data.amount', '2000.00');

        // The website's Pricing page, with no business: each plan's main currency.
        $this->app['auth']->forgetGuards();
        $page = collect($this->getJson('/api/v1/catalog')->json('data.packages'))->keyBy('key');
        $this->assertSame(['USD', '19.50'], [$page['standard']['price_currency'], $page['standard']['price_monthly']]);

        $invoice = $this->subscribe($this->owner, $this->business);
        $this->assertSame(['BDT', '2000.00'], [$invoice->currency, $invoice->amount]);
        $this->assertSame(['BDT', '2000.00'], [$this->business->subscription->currency, $this->business->subscription->price]);
        $this->assertSame('BDT', $this->business->fresh()->base_currency);
    }

    public function test_a_new_price_reaches_new_subscribers_and_existing_ones_only_once_applied(): void
    {
        $this->subscribe($this->owner, $this->business);
        $subscription = $this->business->subscription;

        $this->asAdmin()->postJson(route('admin.packages.price', $this->standard->id), ['currency' => 'USD', 'cycle' => 'monthly', 'amount' => '24', 'starts_on' => '2026-11-10'])->assertOk()
            ->assertJsonPath('message', 'Standard costs USD 24.00 a month from 10 Nov 2026 for new subscribers. Subscribers keep their price until you apply it to them.');
        $this->assertSame('2026-11-09', PackagePrice::where('amount', '19.50')->where('cycle', 'monthly')->sole()->ends_on->toDateString());

        // A business subscribing from then pays the new price; the one before keeps its own.
        $this->travelTo(Carbon::parse('2026-11-10 10:00:00', 'UTC'));
        [$newOwner, $newBusiness] = $this->newBusiness('Uddin Traders');
        $this->assertSame('24.00', $this->subscribe($newOwner, $newBusiness)->amount);

        $this->on('2026-12-01');
        $renewal = SubscriptionInvoice::where('workspace_id', $this->business->id)->where('kind', 'renewal')->sole();
        $this->assertSame('19.50', $renewal->amount);

        // Applied: at least 30 days ahead, with the password and a reason; then each subscriber is told now.
        $apply = fn (array $data) => $this->asAdmin()->postJson(route('admin.packages.price-apply', $this->standard->id), ['currency' => 'USD', 'cycle' => 'monthly', 'from' => '2027-01-01', 'confirm_password' => 'password', 'reason' => 'The price list for 2027', ...$data]);
        $apply(['from' => '2026-12-20'])->assertUnprocessable()->assertJsonValidationErrors('from');
        $apply(['confirm_password' => ''])->assertUnprocessable()->assertJsonValidationErrors('confirm_password');
        $apply([])->assertOk()->assertJsonPath('message', '1 subscriber pays USD 24.00 a month from their first renewal on or after 1 Jan 2027, and each is told now.');

        $this->assertSame(['24.00', '2027-01-01'], [$subscription->fresh()->next_price, $subscription->fresh()->next_price_from->toDateString()]);
        $this->assertSame('The price list for 2027', AdminAuditLog::where('description', 'like', 'Applied plan%')->sole()->reason);
        Mail::assertQueued(SubscriptionNoticeMail::class, fn (SubscriptionNoticeMail $mail) => $mail->kind === SubscriptionNoticeMail::PRICE_CHANGE
            && $mail->hasTo($this->owner->email) && $mail->details['was'] === 'USD 19.50' && $mail->details['now'] === 'USD 24.00' && $mail->details['from'] === '1 Jan 2027');
        $this->asOwner($this->owner)->getJson('/api/v1/subscription', $this->pick($this->business))
            ->assertJsonPath('data.subscription.price', ['currency' => 'USD', 'amount' => '19.50'])->assertJsonPath('data.subscription.next_price', ['amount' => '24.00', 'from' => '2027-01-01']);

        // The renewal before that day keeps the old price; the first on or after it has the new, kept from then.
        $this->asAdmin()->post(route('admin.subscription-invoices.confirm', $renewal->id))->assertRedirect();
        $this->on('2026-12-09');
        $this->on('2027-01-01');
        $next = SubscriptionInvoice::where('workspace_id', $this->business->id)->where('kind', 'renewal')->latest('id')->first();
        $this->assertSame(['2027-01-09', '24.00'], [$next->period_starts_on->toDateString(), $next->amount]);
        $this->assertSame(['24.00', null], [$subscription->fresh()->price, $subscription->fresh()->next_price]);
    }

    public function test_prices_are_kept_as_history_and_one_not_started_can_be_removed(): void
    {
        $add = fn (string $amount, string $from) => $this->asAdmin()->postJson(route('admin.packages.price', $this->standard->id), ['currency' => 'USD', 'cycle' => 'monthly', 'amount' => $amount, 'starts_on' => $from]);

        $add('24', '2026-12-01')->assertOk();
        $add('22', '2026-11-20')->assertUnprocessable()->assertJsonPath('errors.starts_on.0', 'A monthly price in USD starts on or after that day already: remove it first.');
        $add('22', '2026-11-08')->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->get(route('admin.packages.edit', $this->standard->id))->assertOk()->assertSeeInOrder(['USD', 'Monthly', '24.00', 'Starts Dec 01, 2026', 'USD', 'Monthly', '19.50', 'In force']);

        $scheduled = PackagePrice::where('amount', '24.00')->sole();
        $this->delete(route('admin.packages.price-remove', [$this->standard->id, $scheduled->id]))->assertRedirect();
        $this->assertNull(PackagePrice::find($scheduled->id));
        $this->assertNull(PackagePrice::where('amount', '19.50')->where('cycle', 'monthly')->sole()->ends_on);

        $inForce = PackagePrice::where('amount', '19.50')->where('cycle', 'monthly')->sole();
        $this->deleteJson(route('admin.packages.price-remove', [$this->standard->id, $inForce->id]))->assertUnprocessable()->assertJsonPath('errors.price.0', 'A price in force or past is history: it stays. Add a new one to change it.');

        $role = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);
        $this->asAdmin($role('read-only-administrator'))->postJson(route('admin.packages.price', $this->standard->id), ['currency' => 'USD', 'cycle' => 'monthly', 'amount' => '30', 'starts_on' => '2026-12-01'])->assertForbidden();
        $this->asAdmin($role('billing-administrator'))->postJson(route('admin.packages.price', $this->standard->id), ['currency' => 'USD', 'cycle' => 'monthly', 'amount' => '30', 'starts_on' => '2026-12-01'])->assertOk();
    }
}
