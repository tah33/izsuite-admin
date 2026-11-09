<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionTax;
use App\Models\User\User;
use App\Services\Billing\SubscriptionTaxes;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * The tax on izSuite's subscription invoices (Doc 13 section 16, Doc 15
 * section 16) - Doc 13 Phase D1: the admin's rule for the billing country,
 * in force from its day, on the price after any upgrade credit; a reverse
 * charge with a tax ID; shown before choosing, and kept by the invoice.
 */
class SubscriptionTaxTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));

        Package::where('key', 'standard')->update(['price_monthly' => '19.50', 'price_yearly' => '195.00']);
        Package::where('key', 'premium')->update(['price_monthly' => '49.00', 'price_yearly' => '490.00']);

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $this->owner = $this->signIn();
    }

    private function billedTo(string $country, ?string $taxId = null): void
    {
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Uddin Traders Ltd', 'email' => 'accounts@uddin.example', 'address' => 'Dhaka', 'country' => $country, 'tax_id' => $taxId])->assertOk();
    }

    private function rule(array $data): SubscriptionTax
    {
        return SubscriptionTax::create([...['name' => 'VAT', 'starts_on' => '2026-11-01', 'reverse_charge' => false], ...$data]);
    }

    private function quote(string $plan = 'standard', string $cycle = 'monthly')
    {
        return $this->getJson("/api/v1/subscription/quote?package={$plan}&cycle={$cycle}")->assertOk();
    }

    private function asAdmin(): self
    {
        return $this->actingAs($this->admin);
    }

    public function test_the_admin_keeps_one_rule_per_country_and_day(): void
    {
        $form = ['country' => 'bd', 'name' => 'VAT', 'rate' => '15', 'starts_on' => '2026-11-01', 'reverse_charge' => '0'];

        $this->asAdmin()->post(route('admin.subscription-taxes.store'), $form)->assertRedirect(route('admin.subscription-taxes.index'));
        $this->asAdmin()->post(route('admin.subscription-taxes.store'), $form)->assertSessionHasErrors(['starts_on' => 'This country has a rule starting on that day already: edit that one instead.']);
        $this->asAdmin()->post(route('admin.subscription-taxes.store'), [...$form, 'country' => 'XX'])->assertSessionHasErrors('country');
        $this->asAdmin()->post(route('admin.subscription-taxes.store'), [...$form, 'rate' => '101', 'starts_on' => '2026-12-01'])->assertSessionHasErrors('rate');
        $this->asAdmin()->post(route('admin.subscription-taxes.store'), [...$form, 'rate' => '7.5', 'starts_on' => '2027-01-01', 'reverse_charge' => '1', 'reverse_charge_note' => ' '])->assertRedirect();

        $first = SubscriptionTax::whereDate('starts_on', '2026-11-01')->firstOrFail();
        $later = SubscriptionTax::whereDate('starts_on', '2027-01-01')->firstOrFail();

        $this->assertSame(['BD', '15.000', false], [$first->country, (string) $first->rate, $first->reverse_charge]);
        $this->assertSame(['7.5', true, null], [$later->percent(), $later->reverse_charge, $later->reverse_charge_note]);

        // In force today, and the one that takes over from its day.
        $this->asAdmin()->get(route('admin.subscription-taxes.index', ['search' => 'bangla']))->assertOk()->assertSeeInOrder(['Bangladesh', 'Upcoming', 'Bangladesh', 'In force']);
        $this->asAdmin()->get(route('admin.subscription-taxes.index', ['search' => 'germany']))->assertOk()->assertSee('No subscription taxes for that country.');

        // A rule's own day is not taken by itself.
        $this->asAdmin()->put(route('admin.subscription-taxes.update', $first->id), [...$form, 'rate' => '10'])->assertRedirect(route('admin.subscription-taxes.index'));
        $this->assertSame('10.000', (string) $first->fresh()->rate);

        $this->asAdmin()->delete(route('admin.subscription-taxes.destroy', $later->id))->assertRedirect();
        $this->assertNull($later->fresh());
    }

    public function test_an_invoice_carries_its_countrys_tax_and_keeps_it(): void
    {
        $this->rule(['country' => 'BD', 'rate' => '15']);
        $this->billedTo('BD');

        // 15% of 19.50 is 2.925: 2.93.
        $this->quote()->assertJsonPath('data', ['currency' => 'USD', 'amount' => '19.50', 'tax' => ['name' => 'VAT', 'rate' => '15.000', 'amount' => '2.93', 'note' => null], 'total' => '22.43']);

        $page    = $this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'])->assertCreated()->json('data');

        $this->assertSame(['19.50', '22.43', ['name' => 'VAT', 'rate' => '15.000', 'amount' => '2.93', 'note' => null]], [$page['open_invoice']['amount'], $page['open_invoice']['total'], $page['open_invoice']['tax']]);

        // A new rate is for invoices issued after it.
        SubscriptionTax::query()->update(['rate' => '20']);
        $invoice = SubscriptionInvoice::findOrFail($page['open_invoice']['id']);

        $this->assertSame(['VAT', '15.000', '2.93', '22.43'], [$invoice->tax_name, (string) $invoice->tax_rate, (string) $invoice->tax, (string) $invoice->total]);
        $this->assertStringContainsString('VAT (15%)', view('billing.invoice', ['invoice' => $invoice, 'receipt' => false, 'logo' => null])->render());
        $this->asAdmin()->get(route('admin.subscription-invoices.index'))->assertOk()->assertSee('incl. VAT 15%: 2.93');

        // The history lists the total; the price and tax come with the invoice to pay.
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/subscription/invoices')->assertOk()->assertJsonPath('data.0.total', '22.43')->assertJsonMissingPath('data.0.tax');
    }

    public function test_a_tax_id_makes_a_reverse_charge_and_a_rule_counts_from_its_day(): void
    {
        $this->rule(['country' => 'DE', 'rate' => '19', 'reverse_charge' => true]);

        $this->billedTo('DE', 'DE123456789');
        $this->quote()->assertJsonPath('data.tax', ['name' => 'VAT', 'rate' => '0.000', 'amount' => '0.00', 'note' => SubscriptionTax::REVERSE_CHARGE_NOTE])->assertJsonPath('data.total', '19.50');

        $this->billedTo('DE');
        $this->quote()->assertJsonPath('data.tax.amount', '3.71')->assertJsonPath('data.total', '23.21');

        // No rule for the country: no tax. A rule from tomorrow counts from tomorrow.
        $this->billedTo('US');
        $this->rule(['country' => 'US', 'name' => 'Sales tax', 'rate' => '8.875', 'starts_on' => '2026-11-10']);
        $this->quote()->assertJsonPath('data.tax', null)->assertJsonPath('data.total', '19.50');

        $this->travelTo(Carbon::parse('2026-11-10 10:00:00', 'UTC'));
        $this->quote('premium')->assertJsonPath('data.tax.amount', '4.35')->assertJsonPath('data.total', '53.35');
    }

    public function test_an_upgrade_is_taxed_after_its_credit_from_what_was_paid_before_tax(): void
    {
        $this->rule(['country' => 'BD', 'rate' => '15']);
        $this->billedTo('BD');

        $id = $this->postJson('/api/v1/subscription', ['package' => 'standard', 'cycle' => 'monthly'])->assertCreated()->json('data.open_invoice.id');
        $this->asAdmin()->post(route('admin.subscription-invoices.confirm', $id))->assertRedirect();
        Sanctum::actingAs($this->owner);

        // 22.43 paid on 9 Nov, 19.50 of it before tax. On 24 Nov 15 of 30 days are left: 9.75. VAT on 49.00 - 9.75 = 39.25 is 5.89.
        $this->travelTo(Carbon::parse('2026-11-24 10:00:00', 'UTC'));

        $this->getJson('/api/v1/subscription/change?package=premium&cycle=monthly')->assertOk()
            ->assertJsonPath('data.credit', '9.75')
            ->assertJsonPath('data.tax', ['name' => 'VAT', 'rate' => '15.000', 'amount' => '5.89', 'note' => null])
            ->assertJsonPath('data.total', '45.14');

        $this->postJson('/api/v1/subscription/change', ['package' => 'premium', 'cycle' => 'monthly'])->assertOk()
            ->assertJsonPath('data.open_invoice.tax.amount', '5.89')
            ->assertJsonPath('data.open_invoice.total', '45.14');
    }

    public function test_the_tax_is_to_the_cent_half_up(): void
    {
        $this->assertSame(
            ['2.93', '1.50', '0.01', '0.00', '8.88', '0.00'],
            [
                SubscriptionTaxes::percentOf('19.50', '15.000'),
                SubscriptionTaxes::percentOf('10.00', '15'),
                SubscriptionTaxes::percentOf('1.00', '0.5'),
                SubscriptionTaxes::percentOf('0.01', '7.5'),
                SubscriptionTaxes::percentOf('100.00', '8.875'),
                SubscriptionTaxes::percentOf('0.00', '20'),
            ],
        );
    }
}
