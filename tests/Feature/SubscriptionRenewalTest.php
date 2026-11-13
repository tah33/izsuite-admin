<?php

namespace Tests\Feature;

use App\Mail\SubscriptionBillingMail;
use App\Mail\SubscriptionNoticeMail;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\Concerns\PricesPlans;
use Tests\TestCase;

/**
 * A subscription's days (Doc 13 sections 12, 13 and 19) - Doc 13 Phase
 * C2a: its renewal invoice seven days before the period ends; the next
 * period once paid; unpaid, grace with an e-mail a day saying the days left,
 * then the free plan; trials reminding and ending; a cancellation waiting
 * for the period's end, undone until then.
 */
class SubscriptionRenewalTest extends TestCase
{
    use PricesPlans;
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

        Package::where('key', 'standard')->update(['grace_days' => 5]);
        $this->priced('standard', '19.50');
        Package::where('key', 'premium')->update(['trial_days' => 14]);
        $this->priced('premium', '49.00');

        $this->owner    = $this->signIn();
        $this->business = $this->businessOf($this->owner);
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Uddin Traders Ltd', 'email' => 'accounts@uddin.example', 'address' => 'Dhaka', 'country' => 'BD'])->assertOk();
    }

    /** Standard, monthly, paid on 9 Nov: its period runs 9 Nov to 8 Dec. */
    private function activeStandard(): void
    {
        $this->confirm($this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'])->assertCreated()->json('data.open_invoice.id'));
    }

    private function confirm(int $invoiceId): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]))->post(route('admin.subscription-invoices.confirm', $invoiceId))->assertRedirect();
        Sanctum::actingAs($this->owner);
    }

    /** The hourly cycle, run on $day. */
    private function on(string $day): void
    {
        $this->travelTo(Carbon::parse("{$day} 10:00:00", 'UTC'));
        $this->artisan('subscriptions:advance')->assertSuccessful();
    }

    private function subscription(): WorkspaceSubscription
    {
        return $this->business->subscription()->firstOrFail();
    }

    /** @return list<array{0: string, 1: ?int}> each notice queued: its kind, and days left */
    private function notices(): array
    {
        return Mail::queued(SubscriptionNoticeMail::class)->map(fn (SubscriptionNoticeMail $mail) => [$mail->kind, $mail->details['days_left'] ?? null])->values()->all();
    }

    public function test_a_renewal_is_issued_seven_days_ahead_and_a_paid_one_rolls_on(): void
    {
        $this->activeStandard();
        $this->assertSame(['2026-11-09', '2026-12-08'], [$this->subscription()->period_starts_on->toDateString(), $this->subscription()->period_ends_on->toDateString()]);

        $this->on('2026-11-30');
        $this->assertSame(1, SubscriptionInvoice::count());

        $this->on('2026-12-01');
        $this->on('2026-12-02');
        $renewal = SubscriptionInvoice::where('kind', 'renewal')->sole();
        $this->assertSame(['IZS-INV-00002', '2026-12-09', '2027-01-08', '2026-12-08', 'open'], [$renewal->number, $renewal->period_starts_on->toDateString(), $renewal->period_ends_on->toDateString(), $renewal->due_on->toDateString(), $renewal->status]);
        Mail::assertQueued(SubscriptionBillingMail::class, fn ($mail) => ! $mail->receipt && $mail->invoice->is($renewal));

        // Paid ahead: the plan is paid up to the next period's end, which starts as this one ends.
        $this->travelTo(Carbon::parse('2026-12-03 10:00:00', 'UTC'));
        $this->confirm($renewal->id);
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'active')->assertJsonPath('data.subscription.renewed_until', '2027-01-08');

        $this->on('2026-12-09');
        $this->assertSame(['active', '2026-12-09', '2027-01-08'], [$this->subscription()->status, $this->subscription()->period_starts_on->toDateString(), $this->subscription()->period_ends_on->toDateString()]);
        $this->assertSame([], $this->notices());
    }

    public function test_unpaid_it_has_grace_with_an_e_mail_a_day_then_goes_back_to_the_free_plan(): void
    {
        $this->activeStandard();
        $this->on('2026-12-01');

        // The period ended on 8 Dec: grace runs 9 to 13 Dec - "4 days left" on the first day, the last day on the fifth.
        foreach (['2026-12-09', '2026-12-09', '2026-12-10', '2026-12-11', '2026-12-12', '2026-12-13'] as $day) {
            $this->on($day);
        }

        $this->assertSame(['grace', '2026-12-13', 'standard'], [$this->subscription()->status, $this->subscription()->grace_ends_on->toDateString(), $this->business->fresh()->package->key]);
        $this->assertSame([['grace', 4], ['grace', 3], ['grace', 2], ['grace', 1], ['grace', 0]], $this->notices());
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'grace')->assertJsonPath('data.subscription.grace_ends_on', '2026-12-13')->assertJsonPath('data.open_invoice.kind', 'renewal');

        $this->on('2026-12-14');

        $this->assertSame(['cancelled', 'starter'], [$this->subscription()->status, $this->business->fresh()->package->key]);
        $this->assertSame('void', SubscriptionInvoice::where('kind', 'renewal')->sole()->status);
        Mail::assertQueued(SubscriptionNoticeMail::class, fn ($mail) => $mail->kind === 'ended' && $mail->details['reason'] === 'grace' && $mail->hasTo('accounts@uddin.example'));
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'free')->assertJsonPath('data.open_invoice', null);
    }

    public function test_paid_in_grace_the_plan_carries_on_from_where_it_ended(): void
    {
        $this->activeStandard();
        $this->on('2026-12-01');
        $this->on('2026-12-09');

        $this->travelTo(Carbon::parse('2026-12-11 10:00:00', 'UTC'));
        $this->confirm(SubscriptionInvoice::where('kind', 'renewal')->sole()->id);

        $this->assertSame(['active', '2026-12-09', '2027-01-08', null], [$this->subscription()->status, $this->subscription()->period_starts_on->toDateString(), $this->subscription()->period_ends_on->toDateString(), $this->subscription()->grace_ends_on]);

        $this->on('2026-12-12');
        $this->assertSame([['grace', 4]], $this->notices());
    }

    public function test_without_grace_days_the_plan_ends_with_its_period(): void
    {
        Package::where('key', 'standard')->update(['grace_days' => null]);
        $this->activeStandard();

        $this->on('2026-12-09');

        $this->assertSame(['cancelled', 'starter'], [$this->subscription()->status, $this->business->fresh()->package->key]);
        $this->assertSame([['ended', null]], $this->notices());
        $this->assertSame(0, SubscriptionInvoice::where('kind', 'renewal')->count());
    }

    public function test_a_trial_reminds_then_unpaid_goes_back_to_the_free_plan(): void
    {
        // A 14-day trial from 9 Nov: its last day is 22 Nov.
        $this->postJson('/api/v1/subscription', ['package' => 'premium', 'cycle' => 'monthly'])->assertCreated();

        foreach (['2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22'] as $day) {
            $this->on($day);
        }

        $this->assertSame([['trial_ending', 3], ['trial_ending', 1]], $this->notices());
        $this->assertSame('premium', $this->business->fresh()->package->key);

        $this->on('2026-11-23');

        $this->assertSame(['cancelled', 'starter', 'void'], [$this->subscription()->status, $this->business->fresh()->package->key, SubscriptionInvoice::sole()->status]);
        Mail::assertQueued(SubscriptionNoticeMail::class, fn ($mail) => $mail->kind === 'ended' && $mail->details['reason'] === 'trial');
    }

    public function test_a_cancellation_waits_for_the_period_end_and_can_be_undone(): void
    {
        $this->activeStandard();

        $this->travelTo(Carbon::parse('2026-11-20 10:00:00', 'UTC'));
        $this->postJson('/api/v1/subscription/cancel')->assertOk()
            ->assertJsonPath('message', 'Standard stays until 8 Dec 2026. Then your business moves to Billing Starter, the free plan.')
            ->assertJsonPath('data.status', 'cancellation_scheduled');

        $this->on('2026-12-01');
        $this->assertSame(0, SubscriptionInvoice::where('kind', 'renewal')->count());

        // Undone: the renewal, due by now, is issued at once; cancelled again, it is withdrawn.
        $this->postJson('/api/v1/subscription/resume')->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.open_invoice.kind', 'renewal');
        $this->postJson('/api/v1/subscription/cancel')->assertOk()->assertJsonPath('data.open_invoice', null);
        $this->assertSame('void', SubscriptionInvoice::where('kind', 'renewal')->sole()->status);

        $this->on('2026-12-08');
        $this->assertSame('standard', $this->business->fresh()->package->key);

        $this->on('2026-12-09');
        $this->assertSame(['cancelled', 'starter'], [$this->subscription()->status, $this->business->fresh()->package->key]);
        $this->assertSame([['ended', null]], $this->notices());
        $this->postJson('/api/v1/subscription/resume')->assertStatus(409);

        // A trial cancelled ends at once.
        $this->postJson('/api/v1/subscription', ['package' => 'premium', 'cycle' => 'monthly'])->assertCreated();
        $this->postJson('/api/v1/subscription/cancel')->assertOk()->assertJsonPath('data.status', 'free');
        $this->assertSame('starter', $this->business->fresh()->package->key);
    }

    public function test_the_cycle_runs_every_hour(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'subscriptions:advance'));

        $this->assertSame('0 * * * *', $event?->expression);
    }
}
