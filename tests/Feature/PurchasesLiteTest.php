<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\Purchase;
use App\Models\User\TaxRate;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Purchases Lite's purchases (Doc 09, Phase A): a draft added, totalled and
 * changed; recorded - numbered and owed to the supplier (Pay Later); voided
 * instead of deleted; a supplier invoice recorded twice caught; the list's
 * filters; Settings > Purchases; and what a purchase names kept from being
 * deleted. Most tests start from this purchase:
 *
 *   10 reams of paper at 5.00   50.00 + VAT 7.50
 *   courier, no tax             12.00
 *   subtotal 62.00, tax 7.50, total 69.50
 */
class PurchasesLiteTest extends TestCase
{
    use OffersPurchasesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Contact $supplier;

    private Item $paper;

    private TaxRate $vat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerPurchasesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $business = $this->businessOf($this->user);
        $business->forceFill(['tax_mode' => 'enabled'])->save();

        $this->vat      = $business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_purchases' => true, 'effective_from' => '2026-01-01']);
        $this->supplier = $business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Karim Stores', 'supplier_payment_terms' => 15]);
        $this->paper    = $business->items()->create(['type' => 'physical', 'usage' => 'purchase', 'name' => 'Printer Paper', 'unit' => 'Ream', 'purchase_price' => '5.00', 'purchase_tax_id' => $this->vat->id]);
    }

    public function test_a_draft_is_totalled_and_due_on_the_supplier_s_terms(): void
    {
        $this->postJson('/api/v1/purchases', $this->purchase())
            ->assertCreated()
            ->assertJsonPath('message', 'Draft purchase saved.')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.supplier.name', 'Karim Stores')
            ->assertJsonPath('data.supplier_invoice_number', 'KS-1001')
            ->assertJsonPath('data.payment_terms', 15)
            ->assertJsonPath('data.due_date', '2026-11-12')
            ->assertJsonPath('data.subtotal', '62.00')
            ->assertJsonPath('data.grand_total', '69.50')
            ->assertJsonPath('data.taxes.0.tax_amount', '7.50')
            ->assertJsonPath('data.lines.0.tax_rate_id', $this->vat->id)
            ->assertJsonPath('data.actions', ['edit', 'record', 'delete']);

        // A supplier without terms of their own owes on the business's - and the form's own win over both.
        $other = $this->businessOf($this->user)->contacts()->create(['contact_type' => 'business', 'role' => 'both', 'name' => 'Rahman & Sons']);

        $this->postJson('/api/v1/purchases', $this->purchase(['contact_id' => $other->id]))->assertCreated()->assertJsonPath('data.due_date', '2026-11-27');
        $this->putJson('/api/v1/business-settings/purchases', ['payment_terms' => 45, 'notes' => null, 'duplicate_check' => true])->assertOk();
        $this->postJson('/api/v1/purchases', $this->purchase(['contact_id' => $other->id]))->assertCreated()->assertJsonPath('data.due_date', '2026-12-12');
        $this->postJson('/api/v1/purchases', $this->purchase(['payment_terms' => 7]))->assertCreated()->assertJsonPath('data.due_date', '2026-11-04');
        $this->postJson('/api/v1/purchases', $this->purchase(['due_date' => '2026-11-30']))->assertCreated()->assertJsonPath('data.due_date', '2026-11-30');

        // A draft changes freely: the totals follow its lines.
        $id = $this->draft();
        $this->putJson("/api/v1/purchases/{$id}", $this->purchase(['lines' => [$this->paperLine('4')]]))
            ->assertOk()
            ->assertJsonPath('message', 'Purchase saved.')
            ->assertJsonPath('data.grand_total', '23.00');
    }

    public function test_a_purchase_is_made_from_a_supplier_for_what_is_bought(): void
    {
        $business = $this->businessOf($this->user);
        $customer = $business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $sold     = $business->items()->create(['type' => 'service', 'usage' => 'sales', 'name' => 'Consulting', 'sale_price' => '80.00']);
        $salesTax = $business->taxRates()->create(['name' => 'Sales VAT', 'code' => 'SVAT', 'rate' => '15', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);

        $this->postJson('/api/v1/purchases', $this->purchase(['contact_id' => $customer->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_id.0', 'Uddin Traders is only a customer. Choose a supplier, or make them one on their contact page.');

        $this->postJson('/api/v1/purchases', $this->purchase(['lines' => [['item_id' => $sold->id, 'name' => 'Consulting', 'quantity' => '1', 'unit_price' => '80.00']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.item_id' => 'Consulting is only sold, so it cannot be bought.']);

        $this->postJson('/api/v1/purchases', $this->purchase(['lines' => [[...$this->paperLine(), 'tax_rate_id' => $salesTax->id]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.tax_rate_id' => 'Choose one of your purchase taxes.']);

        $this->postJson('/api/v1/purchases', $this->purchase(['lines' => []]))->assertUnprocessable()->assertJsonValidationErrors('lines');
    }

    public function test_recording_numbers_a_draft_and_makes_it_owed(): void
    {
        $id = $this->draft();

        $this->postJson("/api/v1/purchases/{$id}/record")
            ->assertOk()
            ->assertJsonPath('message', 'Purchase PUR-00001 recorded.')
            ->assertJsonPath('data.number', 'PUR-00001')
            ->assertJsonPath('data.status', 'recorded')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.balance_due', '69.50')
            ->assertJsonPath('data.overdue', false)
            ->assertJsonPath('data.actions', ['void']);

        // What it says about the supplier stays as it was recorded.
        $this->supplier->update(['name' => 'Karim Stores Ltd']);
        $this->getJson("/api/v1/purchases/{$id}")->assertJsonPath('data.supplier.name', 'Karim Stores');

        // A recorded purchase is never changed, deleted or recorded again.
        $this->putJson("/api/v1/purchases/{$id}", $this->purchase())
            ->assertStatus(409)
            ->assertJsonPath('message', 'A recorded purchase cannot be changed. Void it and record it again if it is wrong.');
        $this->deleteJson("/api/v1/purchases/{$id}")->assertStatus(409);
        $this->postJson("/api/v1/purchases/{$id}/record")->assertStatus(409);

        $this->postJson('/api/v1/purchases/'.$this->draft(['supplier_invoice_number' => 'KS-1002']).'/record')->assertOk()->assertJsonPath('data.number', 'PUR-00002');
    }

    public function test_a_supplier_invoice_recorded_before_is_recorded_again_only_when_confirmed(): void
    {
        $first = $this->draft();
        $this->postJson("/api/v1/purchases/{$first}/record")->assertOk();

        // The same supplier and invoice number, for another amount: asked once.
        $again = $this->draft(['lines' => [$this->paperLine('2')]]);
        $this->postJson("/api/v1/purchases/{$again}/record")
            ->assertUnprocessable()
            ->assertJsonPath('errors.duplicate.0', 'PUR-00001 already has supplier invoice number KS-1001 from this supplier. Record this one anyway?');
        $this->assertSame('draft', Purchase::find($again)->status->value);
        $this->postJson("/api/v1/purchases/{$again}/record", ['confirmed' => true])->assertOk();

        // The same date, total and currency too: only with a reason, which it keeps.
        $exact = $this->draft();
        $this->postJson("/api/v1/purchases/{$exact}/record", ['confirmed' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.duplicate_reason.0', 'PUR-00001 has the same supplier invoice - number KS-1001, its date, total and currency. Give a reason to record this one too.');
        $this->postJson("/api/v1/purchases/{$exact}/record", ['reason' => 'Two deliveries on one invoice'])
            ->assertOk()
            ->assertJsonPath('data.duplicate_reason', 'Two deliveries on one invoice');

        // Another supplier's invoice of that number is no duplicate, nor is one voided.
        $other = $this->businessOf($this->user)->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Rahman & Sons']);
        $this->postJson('/api/v1/purchases/'.$this->draft(['contact_id' => $other->id]).'/record')->assertOk();

        foreach ([$first, $again, $exact] as $id) {
            $this->postJson("/api/v1/purchases/{$id}/void")->assertOk();
        }
        $this->postJson('/api/v1/purchases/'.$this->draft().'/record')->assertOk();

        // Switched off under Settings > Purchases, nothing is checked.
        $this->putJson('/api/v1/business-settings/purchases', ['payment_terms' => 30, 'notes' => null, 'duplicate_check' => false])->assertOk();
        $this->postJson('/api/v1/purchases/'.$this->draft().'/record')->assertOk();
    }

    public function test_a_recorded_purchase_is_voided_and_a_draft_deleted(): void
    {
        $id = $this->draft();
        $this->postJson("/api/v1/purchases/{$id}/void")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only a recorded purchase can be voided. A draft is deleted instead.');

        $this->postJson("/api/v1/purchases/{$id}/record")->assertOk();
        $this->postJson("/api/v1/purchases/{$id}/void", ['reason' => 'Entered twice'])
            ->assertOk()
            ->assertJsonPath('message', 'Purchase PUR-00001 voided.')
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.payment_status', 'not_applicable')
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.status_reason', 'Entered twice')
            ->assertJsonPath('data.actions', []);

        $draft = $this->draft();
        $this->deleteJson("/api/v1/purchases/{$draft}")->assertOk()->assertJsonPath('message', 'Draft purchase deleted.');
        $this->getJson("/api/v1/purchases/{$draft}")->assertNotFound();
    }

    public function test_the_list_is_searched_and_filtered(): void
    {
        $draft   = $this->draft(['supplier_invoice_number' => 'KS-2002']);
        $overdue = $this->draft(['document_date' => '2026-09-01', 'supplier_invoice_number' => 'KS-0900']);
        $current = $this->draft(['supplier_invoice_number' => 'KS-1001']);
        $voided  = $this->draft(['supplier_invoice_number' => 'KS-0001']);

        foreach ([$overdue, $current, $voided] as $id) {
            $this->postJson("/api/v1/purchases/{$id}/record")->assertOk();
        }
        $this->postJson("/api/v1/purchases/{$voided}/void")->assertOk();

        $ids = fn (string $query) => collect($this->getJson("/api/v1/purchases{$query}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$draft], $ids('?status=draft'));
        $this->assertSame([$overdue, $current], $ids('?payment_status=outstanding'));
        $this->assertSame([$overdue], $ids('?overdue=1'));
        $this->assertSame([$current], $ids('?search=KS-1001'));
        $this->assertSame([$draft, $overdue, $current, $voided], $ids('?search=Karim'));
        $this->assertSame([$overdue], $ids('?date_to=2026-09-30'));

        $rows = collect($this->getJson('/api/v1/purchases')->json('data'))->keyBy('id');
        $this->assertSame([['edit', 'record', 'delete'], ['void'], []], [$rows[$draft]['actions'], $rows[$overdue]['actions'], $rows[$voided]['actions']]);
        $this->assertSame([true, false], [$rows[$overdue]['overdue'], $rows[$draft]['overdue']]);
        $this->assertSame(['Karim Stores', null], [$rows[$current]['supplier'], $rows[$current]['location']]);

        $csv = $this->get('/api/v1/purchases/export?status=recorded')->assertOk()->streamedContent();
        $this->assertStringContainsString('Date,Number,"Supplier Invoice Number",Supplier,Status', $csv);
        $this->assertStringContainsString('2026-09-01,PUR-00001,KS-0900,"Karim Stores",Recorded,Unpaid,Overdue,2026-09-16,USD,69.50,0.00,69.50', $csv);
    }

    public function test_another_business_s_purchase_is_not_found(): void
    {
        $id = $this->draft();

        $this->signIn();

        $this->getJson("/api/v1/purchases/{$id}")->assertNotFound();
        $this->postJson("/api/v1/purchases/{$id}/record")->assertNotFound();
        $this->deleteJson("/api/v1/purchases/{$id}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/purchases')->assertOk()->json('data'));
    }

    public function test_a_business_without_purchases_has_none(): void
    {
        $this->businessOf($this->user)->workspaceApps()->delete();

        $this->getJson('/api/v1/purchases')->assertForbidden();
        $this->postJson('/api/v1/purchases', $this->purchase())->assertForbidden();
        $this->getJson('/api/v1/business-settings/purchases')->assertForbidden();
    }

    public function test_settings_say_what_new_purchases_start_with(): void
    {
        $this->getJson('/api/v1/business-settings/purchases')
            ->assertOk()
            ->assertExactJson(['data' => ['payment_terms' => 30, 'notes' => null, 'duplicate_check' => true]]);

        $this->putJson('/api/v1/business-settings/purchases', ['payment_terms' => 400, 'duplicate_check' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_terms');

        $this->putJson('/api/v1/business-settings/purchases', ['payment_terms' => 10, 'notes' => 'Deliver to the back door.', 'duplicate_check' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Purchase settings saved successfully.')
            ->assertJsonPath('data.duplicate_check', false);

        // The form starts from them - and from the supplier, once one is chosen.
        $this->getJson('/api/v1/purchases/defaults')
            ->assertOk()
            ->assertJsonPath('data.payment_terms', 10)
            ->assertJsonPath('data.due_date', '2026-11-07')
            ->assertJsonPath('data.notes', 'Deliver to the back door.')
            ->assertJsonPath('data.currency', 'USD');

        $this->getJson("/api/v1/purchases/defaults?contact_id={$this->supplier->id}")
            ->assertOk()
            ->assertJsonPath('data.payment_terms', 15)
            ->assertJsonPath('data.due_date', '2026-11-12')
            ->assertJsonPath('data.supplier.id', $this->supplier->id);
    }

    public function test_the_pickers_offer_suppliers_and_what_is_bought(): void
    {
        $business = $this->businessOf($this->user);
        $business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $both = $business->contacts()->create(['contact_type' => 'business', 'role' => 'both', 'name' => 'Ahmed Supplies']);
        $business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Old Supplier', 'is_active' => false]);
        $business->items()->create(['type' => 'service', 'usage' => 'sales', 'name' => 'Consulting', 'sale_price' => '80.00']);
        $ink = $business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Ink', 'sale_price' => '9.00']);

        $this->assertSame([$both->id, $this->supplier->id], collect($this->getJson('/api/v1/contacts?for_purchase=1')->assertOk()->json('data'))->pluck('id')->all());
        $this->assertSame([$ink->id, $this->paper->id], collect($this->getJson('/api/v1/items?for_purchase=1')->assertOk()->json('data'))->pluck('id')->all());
    }

    public function test_what_a_purchase_names_is_deactivated_not_deleted(): void
    {
        $location = $this->businessOf($this->user)->locations()->create(['user_id' => $this->user->id, 'name' => 'Head Office']);
        $id       = $this->draft(['location_id' => $location->id]);

        $this->getJson("/api/v1/contacts/{$this->supplier->id}")->assertJsonPath('data.in_use', true)->assertJsonPath('data.in_sales', false);
        $this->getJson("/api/v1/items/{$this->paper->id}")->assertJsonPath('data.in_use', true)->assertJsonPath('data.in_sales', false);

        $this->deleteJson("/api/v1/contacts/{$this->supplier->id}")->assertStatus(409)->assertJsonPath('message', 'This contact is on purchases, so it cannot be deleted. Deactivate it instead.');
        $this->deleteJson("/api/v1/items/{$this->paper->id}")->assertStatus(409)->assertJsonPath('message', 'This item is on purchases, so it cannot be deleted. Deactivate it instead.');
        $this->deleteJson("/api/v1/locations/{$location->id}")->assertStatus(409)->assertJsonPath('message', 'Purchases were made at this location, so it cannot be deleted.');
        $this->deleteJson("/api/v1/tax-rates/{$this->vat->id}")->assertStatus(409)->assertJsonPath('message', 'This tax is on purchases, so it cannot be deleted. Deactivate it instead.');

        // Once a purchase is recorded, the base currency is its totals' and stays.
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'is_default' => false, 'is_active' => true]);
        $this->postJson("/api/v1/purchases/{$id}/record")->assertOk();

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'EUR', 'additional_currencies' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('base_currency');
    }

    /** The purchase most tests start from, with $fields over it. */
    private function purchase(array $fields = []): array
    {
        return [
            'contact_id'              => $this->supplier->id,
            'document_date'           => '2026-10-28',
            'currency'                => 'USD',
            'supplier_invoice_number' => 'KS-1001',
            'supplier_invoice_date'   => '2026-10-27',
            'lines'                   => [
                $this->paperLine(),
                ['name' => 'Courier', 'quantity' => '1', 'unit_price' => '12.00'],
            ],
            ...$fields,
        ];
    }

    private function paperLine(string $quantity = '10'): array
    {
        return ['item_id' => $this->paper->id, 'name' => 'Printer Paper', 'quantity' => $quantity, 'unit' => 'Ream', 'unit_price' => '5.00', 'tax_rate_id' => $this->vat->id];
    }

    /** A draft purchase - its id. */
    private function draft(array $fields = []): int
    {
        return $this->postJson('/api/v1/purchases', $this->purchase($fields))->assertCreated()->json('data.id');
    }
}
