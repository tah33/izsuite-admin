<?php

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A contact's tax treatment (Doc 03 sections 3 and 8): one of Doc 11's five,
 * or none - kept, filtered on while taxes are on, and taken first for the tax
 * a customer's new sales start with (Doc 11 section 11).
 */
class ContactTaxTreatmentTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    public function test_a_contact_keeps_its_treatment_and_the_list_filters_on_it_while_taxes_are_on(): void
    {
        $this->putJson("/api/v1/contacts/{$this->customer->id}", ['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders', 'tax_treatment' => 'zero_rated'])
            ->assertOk()
            ->assertJsonPath('data.tax_treatment', 'zero_rated');

        $this->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'customer', 'name' => 'Karim Stores', 'tax_treatment' => 'reduced'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tax_treatment');

        $this->businessOf($this->user)->contacts()->create(['name' => 'Karim Stores']);

        $this->getJson('/api/v1/contacts?tax_treatment=zero_rated')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Uddin Traders');

        // While taxes are off the filter is not applied - like the tax ID search.
        $this->businessOf($this->user)->forceFill(['tax_mode' => 'disabled'])->save();
        $this->getJson('/api/v1/contacts?tax_treatment=zero_rated')->assertJsonCount(2, 'data');
    }

    public function test_a_customers_treatment_picks_the_tax_their_sales_start_with(): void
    {
        $business = $this->businessOf($this->user);
        $zero     = $business->taxRates()->create(['name' => 'Exports', 'code' => 'ZR', 'rate' => '0', 'treatment' => 'zero_rated', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        $defaults = fn () => $this->getJson("/api/v1/sales/invoices/defaults?contact_id={$this->customer->id}")->assertOk();

        // None chosen: no override.
        $defaults()->assertJsonPath('data.customer_treatment', null)->assertJsonPath('data.customer_tax_id', null);

        $this->customer->update(['tax_treatment' => 'zero_rated']);
        $defaults()->assertJsonPath('data.customer_treatment', 'zero_rated')->assertJsonPath('data.customer_tax_id', $zero->id);

        // A treatment the business has no rate for: no tax at all, never the standard rate.
        $this->customer->update(['tax_treatment' => 'no_tax']);
        $defaults()->assertJsonPath('data.customer_treatment', 'no_tax')->assertJsonPath('data.customer_tax_id', null);

        // Standard chosen wins over an exemption number; without a choice the number means exempt.
        $this->customer->update(['tax_treatment' => 'standard', 'tax_exemption_number' => 'EX-1']);
        $defaults()->assertJsonPath('data.customer_treatment', null);
        $this->customer->update(['tax_treatment' => null]);
        $defaults()->assertJsonPath('data.customer_treatment', 'exempt');

        // With taxes off nothing is picked.
        $business->forceFill(['tax_mode' => 'disabled'])->save();
        $defaults()->assertJsonPath('data.customer_treatment', null);
    }

    public function test_an_issued_document_keeps_the_treatment_the_customer_had(): void
    {
        $this->customer->update(['tax_treatment' => 'exempt']);

        $id = $this->issuedInvoice();
        $this->customer->update(['tax_treatment' => 'standard']);

        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.customer.tax_treatment', 'exempt');
    }
}
