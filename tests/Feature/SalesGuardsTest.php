<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * What sales documents keep in place: the contacts, items, tax rates and
 * locations on them are deactivated rather than deleted (Doc 03, 04 and 11), a
 * rate on them keeps its percentage, and the base currency is fixed once one
 * is issued or sent (Doc 11 section 7).
 */
class SalesGuardsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    public function test_what_a_document_uses_is_switched_off_rather_than_deleted(): void
    {
        $location = $this->businessOf($this->user)->locations()->create(['name' => 'Main Location', 'user_id' => $this->user->id]);
        $this->draft('invoices', ['location_id' => $location->id]);

        $this->deleteJson("/api/v1/contacts/{$this->customer->id}")->assertConflict()->assertJsonPath('message', 'This contact is on sales documents, so it cannot be deleted. Deactivate it instead.');
        $this->deleteJson("/api/v1/items/{$this->chair->id}")->assertConflict()->assertJsonPath('message', 'This item is on sales documents, so it cannot be deleted. Deactivate it instead.');
        $this->deleteJson("/api/v1/tax-rates/{$this->vat->id}")->assertConflict()->assertJsonPath('message', 'This tax is on sales documents, so it cannot be deleted. Deactivate it instead.');
        $this->deleteJson("/api/v1/locations/{$location->id}")->assertConflict()->assertJsonPath('message', 'Sales documents were made at this location, so it cannot be deleted.');
    }

    public function test_a_rate_on_a_document_keeps_its_percentage(): void
    {
        $this->draft('invoices');

        $fields = ['name' => 'Value Added Tax', 'code' => 'VAT15', 'treatment' => 'standard', 'applies_to' => ['sales'], 'effective_from' => '2026-01-01'];

        $this->putJson("/api/v1/tax-rates/{$this->vat->id}", [...$fields, 'rate' => '16'])
            ->assertJsonValidationErrors(['rate' => 'This rate is on sales documents, so its rate and treatment stay as they are. Add a new rate and switch this one off instead.']);

        $this->putJson("/api/v1/tax-rates/{$this->vat->id}", [...$fields, 'rate' => '15'])->assertOk()->assertJsonPath('data.name', 'Value Added Tax');
    }

    public function test_the_base_currency_is_fixed_once_a_document_is_issued(): void
    {
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'is_default' => false, 'is_active' => true]);

        // A draft does not fix it.
        $id = $this->draft('invoices');
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'EUR'])->assertOk();
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD'])->assertOk();

        $this->postJson("/api/v1/sales/invoices/{$id}/issue")->assertOk();

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'EUR'])
            ->assertJsonValidationErrors(['base_currency' => 'The base currency cannot change once documents have been issued or sent, purchases recorded, or a payment account has an opening balance or money recorded through it.']);
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->assertOk();
    }
}
