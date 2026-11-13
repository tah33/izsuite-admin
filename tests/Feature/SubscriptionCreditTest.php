<?php

namespace Tests\Feature;

use App\Mail\AccountNoticeMail;
use App\Mail\SubscriptionBillingMail;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\SubscriptionCredit;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionTax;
use App\Models\Billing\WorkspaceSubscription;
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
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase C2b (sections 15.13 and 15.14): izSuite's credit to a
 * business - given and taken back from its subscription's page, each with a
 * reason - comes off its next invoices before tax, what is left carrying on;
 * an invoice it covers in full is paid by it; an invoice cancelled gives its
 * credit back. The business sees it in its quotes, invoices and Subscription
 * page.
 */
class SubscriptionCreditTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    private User $admin;

    private WorkspaceSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        Mail::fake();
        Http::fake(['*' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));

        Package::where('key', 'standard')->update(['price_monthly' => '19.50', 'price_yearly' => '195.00', 'grace_days' => 5]);
        Package::where('key', 'premium')->update(['price_monthly' => '49.00', 'price_yearly' => '490.00']);
        PaymentMethod::where('slug', 'bank-transfer')->update(['is_active' => true]);
        // A 10% tax, to show the credit comes off before it.
        SubscriptionTax::create(['country' => 'BD', 'name' => 'VAT', 'rate' => '10', 'starts_on' => '2026-11-01', 'reverse_charge' => false]);

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
        $this->owner    = $this->signIn(User::factory()->create(['first_name' => 'Nadia']));
        $this->business = $this->businessOf($this->owner);
        $this->business->update(['name' => 'Karim Stores']);
        app(SetUpNewWorkspace::class)->handle($this->business, $this->owner, 'api');
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Karim Stores Ltd', 'email' => 'accounts@karim.example', 'address' => 'Dhaka', 'country' => 'BD'])->assertOk();

        // Standard, monthly, paid by transfer on 9 Nov: its period runs to 8 Dec, its renewal comes on 1 Dec.
        $id = $this->asOwner()->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'], $this->pick($this->business))->assertCreated()->json('data.open_invoice.id');
        $this->asAdmin()->post(route('admin.subscription-invoices.confirm', $id))->assertRedirect();
        $this->subscription = $this->business->subscription()->firstOrFail();
    }

    private function asOwner(): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->owner);

        return $this;
    }

    private function asAdmin(?User $admin = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($admin ?? $this->admin);
    }

    private function on(string $day): void
    {
        $this->travelTo(Carbon::parse("{$day} 10:00:00", 'UTC'));
        $this->artisan('subscriptions:advance')->assertSuccessful();
    }

    private function give(string $amount, string $reason = 'Two days without access in November')
    {
        return $this->asAdmin()->postJson(route('admin.subscriptions.credit', $this->subscription->id), ['amount' => $amount, 'reason' => $reason]);
    }

    private function credit(): string
    {
        return (string) SubscriptionCredit::where('workspace_id', $this->business->id)->get()->reduce(fn (string $sum, $row) => bcadd($sum, (string) $row->amount, 2), '0.00');
    }

    private function renewal(): SubscriptionInvoice
    {
        return SubscriptionInvoice::where('kind', SubscriptionInvoice::RENEWAL)->latest('id')->firstOrFail();
    }

    public function test_a_credit_comes_off_the_next_invoices_before_tax_and_one_it_covers_is_paid_by_it(): void
    {
        $this->give('25')->assertOk()->assertJsonPath('message', 'Karim Stores has USD 25.00 of credit, to come off the next invoice.');
        $this->assertSame('Two days without access in November', AdminAuditLog::where('description', 'like', 'Gave business%')->sole()->reason);
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->hasTo($this->owner->email) && $mail->title === 'izSuite has given you a credit of USD 25.00');

        // The renewal: 19.50 before tax, all of it covered - so no tax, nothing to pay, and its receipt in place of the invoice.
        $this->on('2026-12-01');
        $covered = $this->renewal();
        $this->assertSame(['19.50', '19.50', '0.00', '0.00', SubscriptionInvoice::PAID, 'izSuite credit'], [$covered->amount, $covered->adjustment, $covered->tax, $covered->total, $covered->status, $covered->paid_with]);
        $this->assertNotNull($covered->receipt_number);
        Mail::assertQueued(SubscriptionBillingMail::class, fn (SubscriptionBillingMail $mail) => $mail->invoice->is($covered) && $mail->receipt);
        Mail::assertNotQueued(SubscriptionBillingMail::class, fn (SubscriptionBillingMail $mail) => $mail->invoice->is($covered) && ! $mail->receipt);
        $this->assertSame('5.50', $this->credit());

        // The next: what is left comes off before tax - 14.00, and 10% of it.
        $this->on('2026-12-09');
        $this->on('2027-01-01');
        $next = $this->renewal();
        $this->assertSame(['5.50', '1.40', '15.40', SubscriptionInvoice::OPEN], [$next->adjustment, $next->tax, $next->total, $next->status]);
        $this->assertSame('0.00', $this->credit());
        $this->assertSame([SubscriptionCredit::GRANTED, SubscriptionCredit::USED, SubscriptionCredit::USED], SubscriptionCredit::orderBy('id')->pluck('kind')->all());

        $this->asAdmin()->get(route('admin.subscription-invoices.show', $next->id))->assertSeeInOrder(['Subtotal', 'USD 19.50', 'Credit from izSuite', '− USD 5.50', 'Total', 'USD 15.40']);
        $this->asOwner()->getJson('/api/v1/subscription', $this->pick($this->business))->assertJsonPath('data.open_invoice.adjustment', '5.50')->assertJsonPath('data.credits', []);
    }

    public function test_an_invoice_cancelled_gives_its_credit_back_for_the_next(): void
    {
        $this->give('5')->assertOk();
        $this->on('2026-12-01');
        $this->assertSame(['5.00', '15.95'], [$this->renewal()->adjustment, $this->renewal()->total]);
        $this->assertSame('0.00', $this->credit());

        // Scheduling the end cancels the renewal: its credit is the business's again.
        $reason = ['reason' => 'The owner asked by phone'];
        $this->asAdmin()->postJson(route('admin.subscriptions.cancel', $this->subscription->id), $reason)->assertOk();
        $this->assertSame([SubscriptionInvoice::VOID, '5.00'], [$this->renewal()->status, $this->credit()]);

        // Undone, the renewal is issued again - and takes it again.
        $this->postJson(route('admin.subscriptions.undo', $this->subscription->id), $reason)->assertOk();
        $this->assertSame([SubscriptionInvoice::OPEN, '5.00'], [$this->renewal()->status, $this->renewal()->adjustment]);
        $this->assertSame([SubscriptionCredit::GRANTED, SubscriptionCredit::USED, SubscriptionCredit::RETURNED, SubscriptionCredit::USED], SubscriptionCredit::orderBy('id')->pluck('kind')->all());
    }

    public function test_the_business_sees_its_credit_and_the_admin_takes_back_what_is_unused(): void
    {
        $this->give('10')->assertOk();

        $this->asOwner()->getJson('/api/v1/subscription', $this->pick($this->business))->assertJsonPath('data.credits', [['currency' => 'USD', 'amount' => '10.00']]);
        $this->getJson('/api/v1/subscription/change?package=premium&cycle=monthly', $this->pick($this->business))->assertOk()->assertJsonPath('data.adjustment', '10.00');

        $this->asAdmin()->get(route('admin.subscriptions.show', $this->subscription->id))->assertSee('USD 10.00, to come off its next invoices')->assertSee('Take Back Credit');

        $url = route('admin.subscriptions.credit-remove', $this->subscription->id);
        $this->postJson($url, ['amount' => '15', 'reason' => 'Given twice by mistake'])->assertUnprocessable()->assertJsonPath('errors.amount.0', 'No more than USD 10.00 of credit is left to take back.');
        $this->postJson($url, ['amount' => '4', 'reason' => 'Given twice by mistake'])->assertOk()->assertJsonPath('message', "Karim Stores's credit is USD 6.00 now.");
        $this->assertSame('6.00', $this->credit());

        $this->get(route('admin.credits.index'))->assertOk()->assertSeeInOrder(['Karim Stores', 'Taken back', '−USD 4.00', 'Given twice by mistake', 'Karim Stores', 'Given', '+USD 10.00']);
    }

    public function test_each_role_does_only_its_part(): void
    {
        $role = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);

        $readOnly = $role('read-only-administrator');
        $this->asAdmin($readOnly)->get(route('admin.credits.index'))->assertOk();
        $this->get(route('admin.subscriptions.show', $this->subscription->id))->assertOk()->assertDontSee('Give Credit');
        $this->postJson(route('admin.subscriptions.credit', $this->subscription->id), ['amount' => '5', 'reason' => 'Trying it out'])->assertForbidden();

        $this->asAdmin($role('support-administrator'))->get(route('admin.credits.index'))->assertForbidden();

        $this->asAdmin($role('billing-administrator'))->postJson(route('admin.subscriptions.credit', $this->subscription->id), ['amount' => '5', 'reason' => 'An outage in November'])->assertOk();
    }
}
