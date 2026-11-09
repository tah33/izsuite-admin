<?php

namespace Tests\Feature;

use App\Mail\SubscriptionBillingMail;
use App\Models\Admin\Role;
use App\Models\Admin\Setting;
use App\Models\Billing\Package;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Subscribing to a paid plan, paid by bank transfer (Doc 13 sections 11, 12
 * and 15) - Doc 13 Phase C1: an invoice with izSuite's own number, the plan
 * starting once izSuite confirms the payment - or at once, on a trial - a
 * receipt, the billing history and the PDFs, each by e-mail.
 */
class SubscriptionCheckoutTest extends TestCase
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
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));

        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);

        Package::where('key', 'standard')->update(['price_monthly' => '19.50', 'price_yearly' => '195.00']);
        Package::where('key', 'premium')->update(['price_monthly' => '49.00', 'trial_days' => 14]);

        PaymentMethod::create(['type' => 'offline', 'name' => 'Bank Transfer', 'slug' => 'bank-transfer', 'instructions' => '<p>Bank: Example Bank<br>Account: 1234567890</p>', 'is_active' => true]);
        Setting::create(['group' => 'billing', 'key' => 'billing_company_name', 'value' => 'izSuite Ltd']);
        Setting::create(['group' => 'billing', 'key' => 'billing_company_email', 'value' => 'billing@izsuite.test']);

        $this->owner    = $this->signIn();
        $this->business = $this->businessOf($this->owner);
    }

    private function billingDetails(): void
    {
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Uddin Traders Ltd', 'email' => 'accounts@uddin.example', 'address' => 'House 12, Road 5, Dhaka', 'country' => 'BD'])->assertOk();
    }

    private function subscribe(string $package, string $cycle)
    {
        return $this->postJson('/api/v1/subscription', ['package' => $package, 'cycle' => $cycle]);
    }

    private function confirmAsAdmin(int $invoiceId): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]))
            ->post(route('admin.subscription-invoices.confirm', $invoiceId))->assertRedirect();

        Sanctum::actingAs($this->owner);
    }

    /** The HTML of the last PDF asked of Gotenberg. */
    private function sentHtml(): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();

        return (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
    }

    public function test_subscribing_needs_billing_details_and_an_announced_price(): void
    {
        $this->subscribe('standard', 'monthly')->assertJsonValidationErrors(['billing' => 'Add your billing details first']);

        $this->billingDetails();
        $this->subscribe('starter', 'monthly')->assertJsonValidationErrors('package');
        $this->subscribe('premium', 'yearly')->assertJsonValidationErrors(['cycle' => "Premium's yearly price has not been announced yet."]);
        $this->subscribe('standard', 'weekly')->assertJsonValidationErrors('cycle');

        $this->assertSame(0, SubscriptionInvoice::count());
    }

    public function test_the_plan_starts_when_izsuite_confirms_the_bank_transfer(): void
    {
        $this->billingDetails();

        $page    = $this->subscribe('standard', 'yearly')->assertCreated()
            ->assertJsonPath('message', 'Invoice IZS-INV-00001 is issued. Standard starts as soon as izSuite confirms your bank transfer.')
            ->json('data');

        $this->assertSame(['awaiting_payment', 'yearly', 'IZS-INV-00001', '195.00', '2026-11-16'], [$page['status'], $page['subscription']['cycle'], $page['open_invoice']['number'], $page['open_invoice']['total'], $page['open_invoice']['due_on']]);
        $this->assertStringContainsString("Bank Transfer\nBank: Example Bank\nAccount: 1234567890", $page['open_invoice']['payment_instructions']);
        $this->assertSame('starter', $this->business->fresh()->package->key);
        Mail::assertQueued(SubscriptionBillingMail::class, fn ($mail) => ! $mail->receipt && $mail->hasTo('accounts@uddin.example') && $mail->hasTo($this->owner->email));

        // One at a time; the reference matches the transfer to the invoice.
        $this->subscribe('premium', 'monthly')->assertStatus(409);
        $id      = $page['open_invoice']['id'];
        $this->putJson("/api/v1/subscription/invoices/{$id}/reference", ['transfer_reference' => 'TRX-5521'])->assertOk()->assertJsonPath('data.transfer_reference', 'TRX-5521');

        $this->get("/api/v1/subscription/invoices/{$id}/pdf", ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Disposition', 'inline; filename=Invoice-IZS-INV-00001.pdf');
        $html    = $this->sentHtml();
        foreach (['izSuite Ltd', 'IZS-INV-00001', 'Uddin Traders Ltd', 'Bangladesh', 'USD 195.00', 'One year from payment', 'Example Bank'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        // izSuite's logo in place of its name - its own, as none was uploaded - embedded for Gotenberg.
        $this->assertStringContainsString('class="seller__logo" src="data:image/svg+xml;base64,', $html);
        $this->getJson("/api/v1/subscription/invoices/{$id}/pdf?receipt=1")->assertNotFound();

        // izSuite confirms the money arrived: the receipt, and a year of Standard from today.
        $this->confirmAsAdmin($id);

        $invoice = SubscriptionInvoice::findOrFail($id);
        $this->assertSame(['paid', 'IZS-RCT-00001', '2026-11-09', '2027-11-08'], [$invoice->status, $invoice->receipt_number, $invoice->period_starts_on->toDateString(), $invoice->period_ends_on->toDateString()]);
        $this->assertSame('standard', $this->business->fresh()->package->key);
        Mail::assertQueued(SubscriptionBillingMail::class, fn ($mail) => $mail->receipt && $mail->invoice->is($invoice));

        $page    = $this->getJson('/api/v1/subscription')->assertOk()->json('data');
        $this->assertSame(['active', '2027-11-08', null], [$page['status'], $page['subscription']['period_ends_on'], $page['open_invoice']]);

        $this->assertSame([['IZS-INV-00001', 'paid', 'IZS-RCT-00001', 'TRX-5521']], array_map(
            fn (array $row) => [$row['number'], $row['status'], $row['receipt_number'], $row['transfer_reference']],
            $this->getJson('/api/v1/subscription/invoices')->assertOk()->json('data'),
        ));
        $this->get("/api/v1/subscription/invoices/{$id}/pdf?receipt=1", ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Disposition', 'inline; filename=Receipt-IZS-RCT-00001.pdf');
        $this->assertStringContainsString('Paid in full on 9 Nov 2026 by bank transfer, reference TRX-5521', $this->sentHtml());
    }

    public function test_a_trial_starts_at_once_and_its_invoice_is_due_as_it_ends(): void
    {
        $this->billingDetails();

        $page    = $this->subscribe('premium', 'monthly')->assertCreated()
            ->assertJsonPath('message', 'Your 14-day Premium trial has started. Invoice IZS-INV-00001 is due by 22 Nov 2026.')
            ->json('data');

        $this->assertSame(['trialing', '2026-11-22', false], [$page['status'], $page['subscription']['trial_ends_on'], $page['trial_available']]);
        $this->assertSame('premium', $this->business->fresh()->package->key);

        $invoice = SubscriptionInvoice::sole();
        $this->assertSame(['2026-11-22', '2026-11-23', '2026-12-22'], [$invoice->due_on->toDateString(), $invoice->period_starts_on->toDateString(), $invoice->period_ends_on->toDateString()]);

        // Paid during the trial: the paid month follows it.
        $this->confirmAsAdmin($invoice->id);
        $this->assertSame(['active', '2026-11-23'], [$this->getJson('/api/v1/subscription')->json('data.status'), $this->getJson('/api/v1/subscription')->json('data.subscription.period_starts_on')]);
    }

    public function test_an_invoice_not_yet_paid_can_be_withdrawn_or_cancelled(): void
    {
        $this->billingDetails();
        $this->subscribe('standard', 'monthly')->assertCreated();

        $this->postJson('/api/v1/subscription/withdraw')->assertOk()
            ->assertJsonPath('message', 'Invoice IZS-INV-00001 is withdrawn. Choose a plan again whenever you are ready.')
            ->assertJsonPath('data.status', 'free')
            ->assertJsonPath('data.open_invoice', null);
        $this->postJson('/api/v1/subscription/withdraw')->assertStatus(409);

        // Chosen again: a new number; cancelled by izSuite, it cannot then be paid.
        $id    = $this->subscribe('standard', 'monthly')->assertCreated()->json('data.open_invoice.id');
        $this->assertSame('IZS-INV-00002', SubscriptionInvoice::findOrFail($id)->number);

        $admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $this->actingAs($admin)->post(route('admin.subscription-invoices.void', $id))->assertRedirect();
        $this->actingAs($admin)->from(route('admin.subscription-invoices.index'))->post(route('admin.subscription-invoices.confirm', $id))
            ->assertRedirect(route('admin.subscription-invoices.index'))->assertSessionHasErrors(['invoice' => 'Only an open invoice can be marked paid.']);

        $this->actingAs($admin)->get(route('admin.subscription-invoices.index', ['status' => 'void']))->assertOk()->assertSee('IZS-INV-00002')->assertSee('IZS-INV-00001');
        $this->assertSame('starter', $this->business->fresh()->package->key);
    }

    public function test_the_e_mails_carry_the_invoice_and_the_receipt(): void
    {
        $this->billingDetails();
        $invoice = SubscriptionInvoice::findOrFail($this->subscribe('standard', 'yearly')->json('data.open_invoice.id'));

        (new SubscriptionBillingMail($invoice))
            ->assertHasSubject('Invoice IZS-INV-00001 for your Standard subscription')
            ->assertSeeInHtml('USD 195.00')
            ->assertSeeInHtml('16 Nov 2026')
            ->assertHasAttachment(Attachment::fromData(fn () => '%PDF-1.7 fake', 'Invoice-IZS-INV-00001.pdf')->withMime('application/pdf'));

        $this->confirmAsAdmin($invoice->id);

        (new SubscriptionBillingMail($invoice->fresh(), receipt: true))
            ->assertHasSubject('Receipt IZS-RCT-00001: payment received for Standard')
            ->assertSeeInHtml('active from 9 Nov 2026 to 8 Nov 2027')
            ->assertHasAttachment(Attachment::fromData(fn () => '%PDF-1.7 fake', 'Receipt-IZS-RCT-00001.pdf')->withMime('application/pdf'));
    }

    public function test_izsuite_s_details_on_its_invoices_are_set_in_the_admin(): void
    {
        $admin   = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);

        $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'billing']))->assertOk()->assertSee('Days to pay an invoice');
        $this->actingAs($admin)->put(route('admin.settings.update'), ['_group' => 'billing', 'settings' => [
            'billing_company_name' => 'izSuite Ltd', 'billing_company_address' => "Level 5, Road 11\nDhaka", 'billing_company_email' => 'billing@izsuite.test', 'billing_company_tax_id' => 'BIN-1100', 'billing_invoice_due_days' => '10',
        ]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('admin.settings.update'), ['_group' => 'billing', 'settings' => ['billing_company_name' => '', 'billing_invoice_due_days' => '0']])
            ->assertSessionHasErrors(['settings.billing_company_name', 'settings.billing_invoice_due_days']);

        Sanctum::actingAs($this->owner);
        $this->billingDetails();
        $invoice = SubscriptionInvoice::findOrFail($this->subscribe('standard', 'monthly')->json('data.open_invoice.id'));

        $this->assertSame(['izSuite Ltd', "Level 5, Road 11\nDhaka", 'BIN-1100', '2026-11-19'], [$invoice->seller['name'], $invoice->seller['address'], $invoice->seller['tax_id'], $invoice->due_on->toDateString()]);
    }

    public function test_billing_is_for_roles_with_manage_subscription(): void
    {
        $role   = $this->business->roles()->create(['name' => 'Settings Viewer', 'permissions' => Permissions::expand(['settings.view'])]);
        $member = User::factory()->create();
        $this->business->members()->create(['user_id' => $member->id, 'role_id' => $role->id, 'name' => $member->name, 'email' => $member->email, 'status' => 'active']);
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'], $this->pick($this->business))->assertForbidden();
        $this->getJson('/api/v1/subscription/invoices', $this->pick($this->business))->assertForbidden();
    }
}
