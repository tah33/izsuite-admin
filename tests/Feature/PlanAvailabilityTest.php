<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\User\User;
use App\Models\User\Workspace;
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
 * Doc 15 Phase E3a (section 15.8): a plan's reporting and support levels,
 * the cycles it is offered on and the billing countries it is offered in.
 * A business subscribes to, or moves to, a plan only where and as it is
 * offered; one on it already keeps it, and renews on its cycle.
 */
class PlanAvailabilityTest extends TestCase
{
    use PricesPlans;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $admin;

    private User $owner;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        Http::fake(['*' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));

        $this->priced('standard', '19.50', '195.00');
        $this->priced('premium', '49.00', '490.00');

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $this->owner    = $this->signIn();
        $this->business = $this->businessOf($this->owner);
        $this->billedIn('BD');
    }

    private function billedIn(string $country): void
    {
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Uddin Traders Ltd', 'email' => 'accounts@uddin.example', 'address' => 'Dhaka', 'country' => $country])->assertOk();
    }

    private function plan(string $key): Package
    {
        return Package::where('key', $key)->sole();
    }

    /** $data saved on plan $key in the admin - the whole form, as it sends it. */
    private function edit(string $key, array $data)
    {
        $plan = $this->plan($key);
        $form = ['name' => $plan->name, 'price_currency' => $plan->price_currency, 'is_active' => 1, 'offers_monthly' => 0, 'offers_yearly' => 0];

        $response = $this->actingAs($this->admin)->putJson(route('admin.packages.update', $plan->id), [...$form, ...$data]);
        Sanctum::actingAs($this->owner);

        return $response;
    }

    private function confirm(int $invoiceId): void
    {
        $this->actingAs($this->admin)->post(route('admin.subscription-invoices.confirm', $invoiceId))->assertRedirect();
        Sanctum::actingAs($this->owner);
    }

    public function test_a_plan_is_given_its_levels_cycles_and_countries_in_the_admin(): void
    {
        $premium = $this->plan('premium');

        // As the plans stood: Premium has advanced reports and priority support.
        $this->assertSame(['advanced', 'priority', true, true, null], [$premium->reporting_level, $premium->support_level, $premium->offers_monthly, $premium->offers_yearly, $premium->countries]);
        $this->actingAs($this->admin)->get(route('admin.packages.edit', $premium->id))->assertOk()->assertSee('Offered in')->assertSee('Priority');

        $this->edit('premium', ['reporting_level' => 'advanced', 'support_level' => 'dedicated', 'offers_monthly' => 1, 'countries' => ['BD', 'IN', 'BD']])->assertOk();

        $premium->refresh();
        $this->assertSame(['dedicated', true, false, ['BD', 'IN']], [$premium->support_level, $premium->offers_monthly, $premium->offers_yearly, $premium->countries]);
        $this->actingAs($this->admin)->get(route('admin.packages.index'))->assertOk()->assertSee('Monthly only')->assertSee('In 2 countries');

        // One cycle at least; levels and countries from the lists.
        $this->edit('premium', [])->assertJsonValidationErrors(['offers_monthly' => 'Offer the plan monthly, yearly or both.']);
        $this->edit('premium', ['offers_yearly' => 1, 'support_level' => 'phone', 'countries' => ['XX']])->assertJsonValidationErrors(['support_level', 'countries.0']);

        // None chosen is every country; a form without the switches leaves the cycles as they are.
        $this->actingAs($this->admin)->putJson(route('admin.packages.update', $premium->id), ['name' => 'Premium', 'is_active' => 1])->assertOk();
        $this->assertSame([true, false, null], [$premium->fresh()->offers_monthly, $premium->fresh()->offers_yearly, $premium->fresh()->countries]);

        // Billing Starter is offered everywhere, on no cycle of its own.
        $this->edit('starter', ['reporting_level' => 'basic', 'countries' => ['IN']])->assertOk();
        $this->assertSame(['basic', true, true, null], [$this->plan('starter')->reporting_level, $this->plan('starter')->offers_monthly, $this->plan('starter')->offers_yearly, $this->plan('starter')->countries]);
    }

    public function test_the_pricing_page_shows_the_levels_the_cycles_and_where_a_plan_is_offered(): void
    {
        $this->plan('premium')->update(['offers_yearly' => false, 'countries' => ['IN', 'NP'], 'support_level' => null]);

        $plans = collect($this->getJson('/api/v1/catalog')->assertOk()->json('data.packages'))->keyBy('key');

        $this->assertSame(['Standard', 'E-mail', ['monthly', 'yearly'], '195.00', []], [$plans['standard']['reporting_level'], $plans['standard']['support_level'], $plans['standard']['cycles'], $plans['standard']['price_yearly'], $plans['standard']['offered_in']]);
        // Not offered yearly: no yearly price, though one is set.
        $this->assertSame(['Advanced', null, ['monthly'], '49.00', null, ['India', 'Nepal']], [
            $plans['premium']['reporting_level'], $plans['premium']['support_level'], $plans['premium']['cycles'], $plans['premium']['price_monthly'], $plans['premium']['price_yearly'], $plans['premium']['offered_in'],
        ]);
    }

    public function test_a_business_subscribes_only_where_and_as_a_plan_is_offered(): void
    {
        $this->plan('premium')->update(['offers_yearly' => false]);

        $this->getJson('/api/v1/subscription/quote?package=premium&cycle=yearly')->assertJsonValidationErrors(['cycle' => 'Premium is not offered yearly.']);
        $this->postJson('/api/v1/subscription', ['package' => 'premium', 'cycle' => 'yearly'])->assertJsonValidationErrors(['cycle' => 'Premium is not offered yearly.']);

        // Offered in India only: a business billed in Bangladesh does not see it, nor subscribe to it.
        $this->plan('premium')->update(['countries' => ['IN']]);

        $page = $this->getJson('/api/v1/subscription')->assertOk()->json('data.packages');
        $this->assertSame(['starter', 'standard'], array_column($page, 'key'));
        $this->assertArrayNotHasKey('offered_in', $page[0]);
        $this->postJson('/api/v1/subscription', ['package' => 'premium', 'cycle' => 'monthly'])->assertJsonValidationErrors(['package' => 'Premium is not offered where your business is billed.']);

        // Billed in India, it may.
        $this->billedIn('IN');

        $this->assertSame(['starter', 'standard', 'premium'], array_column($this->getJson('/api/v1/subscription')->json('data.packages'), 'key'));
        $this->postJson('/api/v1/subscription', ['package' => 'premium', 'cycle' => 'monthly'])->assertCreated();
        $this->assertSame(0, SubscriptionInvoice::where('package_name', 'Premium')->where('cycle', 'yearly')->count());
    }

    public function test_a_plan_change_goes_only_where_and_as_a_plan_is_offered_and_a_subscriber_keeps_its_own(): void
    {
        $this->confirm($this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'])->assertCreated()->json('data.open_invoice.id'));

        $this->plan('premium')->update(['countries' => ['IN']]);
        $this->plan('standard')->update(['offers_monthly' => false, 'countries' => ['IN']]);

        $this->getJson('/api/v1/subscription/change?package=premium&cycle=monthly')->assertJsonValidationErrors(['package' => 'Premium is not offered where your business is billed.']);
        $this->postJson('/api/v1/subscription/change', ['package' => 'premium', 'cycle' => 'monthly'])->assertJsonValidationErrors('package');

        // Its own plan, no longer offered where it is billed, nor monthly: still shown as its own...
        $page = $this->getJson('/api/v1/subscription')->assertOk()->json('data');
        $this->assertSame([['starter', false], ['standard', true]], array_map(fn (array $plan) => [$plan['key'], $plan['current']], $page['packages']));
        $this->assertSame(['yearly'], $page['packages'][1]['cycles']);

        // ...and renewed on its cycle, a week before the period ends.
        $this->travelTo(Carbon::parse('2026-12-01 10:00:00', 'UTC'));
        $this->artisan('subscriptions:advance')->assertSuccessful();

        $renewal = SubscriptionInvoice::where('kind', SubscriptionInvoice::RENEWAL)->sole();
        $this->assertSame(['Standard', 'monthly', '19.50'], [$renewal->package_name, $renewal->cycle, (string) $renewal->amount]);
    }
}
