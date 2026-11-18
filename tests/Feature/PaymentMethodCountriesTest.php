<?php

namespace Tests\Feature;

use App\Models\Admin\Country;
use App\Models\Admin\Role;
use App\Models\Admin\Setting;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceCard;
use App\Models\User\User;
use App\Services\Billing\CardPayments;
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
 * Doc 15 Phase F3b (sections 15.15 and 15.19): each of izSuite's ways to pay
 * offered in chosen billing countries - none, every country. A business
 * billed elsewhere is not offered it: an offline method's instructions stay
 * off its invoices, and cards are neither taken nor charged. Stripe is never
 * called. Saturday 14 November 2026.
 */
class PaymentMethodCountriesTest extends TestCase
{
    use PricesPlans;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));

        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);

        $this->priced('standard', '19.50', '195.00');
        Setting::create(['group' => 'billing', 'key' => 'billing_company_name', 'value' => 'izSuite Ltd']);
        Setting::create(['group' => 'billing', 'key' => 'billing_company_email', 'value' => 'billing@izsuite.test']);

        PaymentMethod::where('slug', 'stripe')->firstOrFail()->update([
            'is_active'   => true,
            'is_sandbox'  => true,
            'credentials' => ['test' => ['publishable_key' => 'pk_test_123', 'secret_key' => 'sk_test_123', 'webhook_secret' => 'whsec_test']],
        ]);
        PaymentMethod::create(['type' => 'offline', 'name' => 'bKash', 'slug' => 'bkash', 'instructions' => '<p>bKash merchant 01700000000</p>', 'is_active' => true, 'sort_order' => 2, 'countries' => ['BD']]);

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
    }

    /** A business signed into, billed in $country, with Standard's invoice open. */
    private function billedIn(string $country): SubscriptionInvoice
    {
        $owner = $this->signIn(User::factory()->create());

        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Uddin Traders Ltd', 'email' => 'accounts@uddin.example', 'address' => 'House 12', 'country' => $country])->assertOk();
        $this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'])->assertCreated();

        return SubscriptionInvoice::where('workspace_id', $this->businessOf($owner)->id)->sole();
    }

    public function test_an_invoice_says_how_to_pay_by_the_methods_offered_where_the_business_is_billed(): void
    {
        $india = $this->billedIn('IN');
        $this->assertStringContainsString('Bank Transfer', $india->payment_instructions);
        $this->assertStringNotContainsString('bKash', $india->payment_instructions);

        $bangladesh = $this->billedIn('BD');
        $this->assertStringContainsString('Bank Transfer', $bangladesh->payment_instructions);
        $this->assertStringContainsString('bKash merchant 01700000000', $bangladesh->payment_instructions);
    }

    public function test_cards_are_taken_and_charged_only_where_they_are_offered(): void
    {
        PaymentMethod::where('slug', 'stripe')->firstOrFail()->update(['countries' => ['IN', 'US']]);

        $invoice = $this->billedIn('BD');
        WorkspaceCard::create([
            'workspace_id' => $invoice->workspace_id, 'provider' => 'stripe', 'livemode' => false, 'customer_id' => 'cus_uddin',
            'card_id'      => 'pm_visa', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 8, 'exp_year' => 2030,
        ]);

        $this->getJson('/api/v1/subscription')->assertOk()->assertJsonPath('data.card_payments', false);
        $this->postJson("/api/v1/subscription/invoices/{$invoice->id}/card-checkout")->assertStatus(409)
            ->assertJsonPath('message', 'Card payments are not offered where your business is billed. Pay as your invoice says instead.');
        $this->postJson('/api/v1/subscription/card')->assertStatus(409);

        // The saved card is not charged either: nothing to try, and Stripe is never called.
        $this->assertNull(app(CardPayments::class)->charge($invoice, '2026-11-14'));

        // Offered everywhere again: cards are taken.
        PaymentMethod::where('slug', 'stripe')->firstOrFail()->update(['countries' => null]);
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.card_payments', true);
    }

    public function test_the_admin_offers_a_way_to_pay_in_chosen_countries(): void
    {
        $bank = PaymentMethod::where('slug', 'bank-transfer')->firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.payment-methods.index'))->assertOk()->assertSee('Offered in Bangladesh')->assertSee('Every country');

        // It takes real payments, so a change needs the password again and a reason (Doc 15 section 15.25).
        $kept = ['name' => 'Bank Transfer', 'instructions' => $bank->instructions, 'is_active' => '1', 'confirm_password' => 'password', 'reason' => 'Our banks take transfers from India and Bangladesh'];

        $this->put(route('admin.payment-methods.update', $bank->id), [...$kept, 'countries' => ['IN', 'BD', 'IN']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['IN', 'BD'], $bank->fresh()->countries);
        $this->get(route('admin.payment-methods.edit', $bank->id))->assertSee('<option value="IN" selected>India</option>', false);

        // None chosen: every country again. A code that is no country is refused.
        $this->put(route('admin.payment-methods.update', $bank->id), $kept)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($bank->fresh()->countries);
        $this->put(route('admin.payment-methods.update', $bank->id), ['name' => 'Bank Transfer', 'countries' => ['XX']])->assertSessionHasErrors('countries.0');

        // A country's page lists the ways to pay offered to a business billed there, in their order (Bank Transfer's is now 0).
        $india = Country::where('code', 'IN')->sole();
        $this->get(route('admin.countries.edit', $india->id))->assertSee('Ways to pay offered to a business billed here: Bank Transfer, Stripe.');
        $bangladesh = Country::where('code', 'BD')->sole();
        $this->get(route('admin.countries.edit', $bangladesh->id))->assertSee('Ways to pay offered to a business billed here: Bank Transfer, Stripe, bKash.');
    }
}
