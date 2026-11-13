<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Changing an active subscription's plan (Doc 13 section 13) - Doc 13 Phase
 * C2b: up at once, its price shown first less a credit for the unused days,
 * starting when paid; down - or yearly to monthly - when the paid period
 * ends, its renewal on the new plan, undone until then.
 */
class SubscriptionChangeTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));

        Package::where('key', 'standard')->update(['price_monthly' => '19.50', 'price_yearly' => '195.00', 'grace_days' => 5]);
        Package::where('key', 'premium')->update(['price_monthly' => '49.00', 'price_yearly' => '490.00', 'grace_days' => 5]);

        $this->owner    = $this->signIn();
        $this->business = $this->businessOf($this->owner);
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Uddin Traders Ltd', 'email' => 'accounts@uddin.example', 'address' => 'Dhaka', 'country' => 'BD'])->assertOk();
    }

    /** $plan, $cycle, paid on 9 Nov. */
    private function active(string $plan, string $cycle = 'monthly'): void
    {
        $this->confirm($this->postJson('/api/v1/subscription', ['package' => $plan, 'cycle' => $cycle])->assertCreated()->json('data.open_invoice.id'));
    }

    private function confirm(int $invoiceId): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]))->post(route('admin.subscription-invoices.confirm', $invoiceId))->assertRedirect();
        Sanctum::actingAs($this->owner);
    }

    private function on(string $day): void
    {
        $this->travelTo(Carbon::parse("{$day} 10:00:00", 'UTC'));
        $this->artisan('subscriptions:advance')->assertSuccessful();
    }

    private function preview(string $plan, string $cycle)
    {
        return $this->getJson("/api/v1/subscription/change?package={$plan}&cycle={$cycle}");
    }

    private function subscription(): WorkspaceSubscription
    {
        return $this->business->subscription()->firstOrFail();
    }

    public function test_an_upgrade_is_priced_less_the_unused_days_and_starts_when_paid(): void
    {
        // Standard, 9 Nov to 8 Dec - 30 days at 19.50. On 24 Nov, 15 of them are left: 9.75.
        $this->active('standard');
        $this->travelTo(Carbon::parse('2026-11-24 10:00:00', 'UTC'));

        $this->preview('premium', 'monthly')->assertOk()->assertJsonPath('data', [
            'kind' => 'upgrade', 'package' => 'Premium', 'cycle' => 'monthly', 'currency' => 'USD', 'amount' => '49.00', 'credit' => '9.75', 'adjustment' => '0.00', 'tax' => null, 'total' => '39.25', 'effective_on' => null,
        ]);

        $page = $this->postJson('/api/v1/subscription/change', ['package' => 'premium', 'cycle' => 'monthly'])->assertOk()
            ->assertJsonPath('message', 'Invoice IZS-INV-00002 is issued for USD 39.25. Premium starts as soon as izSuite confirms your payment.')
            ->json('data');

        $this->assertSame(['active', 'upgrade', '9.75', '39.25'], [$page['status'], $page['open_invoice']['kind'], $page['open_invoice']['credit'], $page['open_invoice']['total']]);
        $this->assertSame('standard', $this->business->fresh()->package->key);
        $this->postJson('/api/v1/subscription/change', ['package' => 'premium', 'cycle' => 'yearly'])->assertStatus(409);

        // The PDF shows the credit.
        $this->get("/api/v1/subscription/invoices/{$page['open_invoice']['id']}/pdf", ['Accept' => 'application/json'])->assertOk();

        // Paid on 26 Nov: Premium for a month from then.
        $this->travelTo(Carbon::parse('2026-11-26 10:00:00', 'UTC'));
        $this->confirm($page['open_invoice']['id']);

        $this->assertSame(['premium', 'monthly', '2026-11-26', '2026-12-25'], [$this->business->fresh()->package->key, $this->subscription()->cycle, $this->subscription()->period_starts_on->toDateString(), $this->subscription()->period_ends_on->toDateString()]);
    }

    public function test_monthly_to_yearly_is_an_upgrade_and_an_unpaid_one_lapses(): void
    {
        $this->active('standard');
        $this->travelTo(Carbon::parse('2026-11-24 10:00:00', 'UTC'));

        $this->preview('standard', 'yearly')->assertJsonPath('data.kind', 'upgrade')->assertJsonPath('data.total', '185.25');
        $this->preview('standard', 'monthly')->assertJsonValidationErrors(['package' => 'already']);
        $this->preview('starter', 'monthly')->assertJsonValidationErrors(['package' => 'cancel your subscription']);

        $this->postJson('/api/v1/subscription/change', ['package' => 'standard', 'cycle' => 'yearly'])->assertOk();

        // Withdrawn, it can be asked again; left unpaid, it lapses as the period ends - and the renewal comes then.
        $this->postJson('/api/v1/subscription/withdraw')->assertOk()->assertJsonPath('message', 'Invoice IZS-INV-00002 is withdrawn. You stay on Standard.');
        $this->postJson('/api/v1/subscription/change', ['package' => 'standard', 'cycle' => 'yearly'])->assertOk();

        $this->on('2026-12-01');
        $this->assertSame(0, SubscriptionInvoice::where('kind', 'renewal')->count());

        $this->on('2026-12-09');
        $this->assertSame(['void', 'void'], SubscriptionInvoice::where('kind', 'upgrade')->orderBy('id')->pluck('status')->all());
        $this->assertSame(['grace', 'monthly'], [$this->subscription()->status, $this->subscription()->cycle]);
        $this->assertSame('open', SubscriptionInvoice::where('kind', 'renewal')->sole()->status);
    }

    public function test_from_yearly_an_upgrade_stays_yearly(): void
    {
        $this->active('standard', 'yearly');

        $this->preview('premium', 'monthly')->assertJsonValidationErrors(['cycle' => 'Choose the yearly price']);
        $this->preview('premium', 'yearly')->assertOk()->assertJsonPath('data.kind', 'upgrade');
        $this->preview('standard', 'monthly')->assertOk()->assertJsonPath('data.kind', 'downgrade')->assertJsonPath('data.effective_on', '2027-11-09');
    }

    public function test_a_downgrade_waits_for_the_period_end_and_renews_on_the_lower_plan(): void
    {
        $this->active('premium');

        $this->preview('standard', 'monthly')->assertJsonPath('data', [
            'kind' => 'downgrade', 'package' => 'Standard', 'cycle' => 'monthly', 'currency' => 'USD', 'amount' => '19.50', 'credit' => '0.00', 'adjustment' => '0.00', 'tax' => null, 'total' => '19.50', 'effective_on' => '2026-12-09',
        ]);
        $this->postJson('/api/v1/subscription/change', ['package' => 'standard', 'cycle' => 'monthly'])->assertOk()
            ->assertJsonPath('message', 'Your plan moves to Standard, monthly, on 9 Dec 2026, when this paid period ends. Until then you keep Premium.')
            ->assertJsonPath('data.subscription.scheduled_change', ['package' => 'Standard', 'cycle' => 'monthly', 'on' => '2026-12-09']);

        // Its renewal is for Standard; paid, Standard starts as Premium's period ends.
        $this->on('2026-12-01');
        $renewal = SubscriptionInvoice::where('kind', 'renewal')->sole();
        $this->assertSame(['Standard', '19.50'], [$renewal->package_name, $renewal->total]);
        $this->confirm($renewal->id);
        $this->assertSame('premium', $this->business->fresh()->package->key);

        $this->on('2026-12-09');
        $this->assertSame(['standard', 'active', '2026-12-09', '2027-01-08', null], [
            $this->business->fresh()->package->key, $this->subscription()->status, $this->subscription()->period_starts_on->toDateString(), $this->subscription()->period_ends_on->toDateString(), $this->subscription()->scheduled_package_id,
        ]);
    }

    public function test_a_downgrade_is_undone_or_replaced_by_a_cancellation(): void
    {
        $this->active('premium');
        $this->on('2026-12-01');
        $this->assertSame('Premium', SubscriptionInvoice::where('kind', 'renewal')->sole()->package_name);

        // Scheduled within the renewal's days: Premium's renewal gives way to Standard's - and back when undone.
        $this->postJson('/api/v1/subscription/change', ['package' => 'standard', 'cycle' => 'monthly'])->assertOk()->assertJsonPath('data.open_invoice.package', 'Standard');
        $this->deleteJson('/api/v1/subscription/change')->assertOk()
            ->assertJsonPath('message', 'You stay on Premium, monthly.')
            ->assertJsonPath('data.subscription.scheduled_change', null)
            ->assertJsonPath('data.open_invoice.package', 'Premium');
        $this->assertSame(['void', 'void', 'open'], SubscriptionInvoice::where('kind', 'renewal')->orderBy('id')->pluck('status')->all());
        $this->deleteJson('/api/v1/subscription/change')->assertStatus(409);

        // A cancellation takes a move down's place.
        $this->postJson('/api/v1/subscription/change', ['package' => 'standard', 'cycle' => 'monthly'])->assertOk();
        $this->postJson('/api/v1/subscription/cancel')->assertOk()->assertJsonPath('data.status', 'cancellation_scheduled')->assertJsonPath('data.subscription.scheduled_change', null);

        $this->on('2026-12-09');
        $this->assertSame(['cancelled', 'starter'], [$this->subscription()->status, $this->business->fresh()->package->key]);
    }

    public function test_only_an_active_subscription_changes_plan(): void
    {
        $this->preview('standard', 'monthly')->assertStatus(409)->assertJsonPath('message', 'Choose a plan first.');

        $this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'])->assertCreated();
        $this->preview('premium', 'monthly')->assertStatus(409)->assertJsonPath('message', 'Your plan has not started yet: withdraw its invoice to choose another.');
    }
}
