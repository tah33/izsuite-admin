<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Sales Lite's documents as drafts (Doc 08 sections 4 to 10): made and
 * changed with their totals worked out on the server, checked, listed and
 * filtered, and deleted while still drafts.
 */
class SalesDocumentsApiTest extends TestCase
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

    public function test_a_draft_invoice_has_its_totals_worked_out_on_the_server(): void
    {
        $data = $this->postJson('/api/v1/sales/invoices', [...$this->invoice(), 'grand_total' => '1.00'])
            ->assertCreated()
            ->assertJsonPath('message', 'Draft invoice saved.')
            ->json('data');

        $this->assertSame(['invoice', 'draft', null, 'not_applicable', ['edit', 'issue', 'delete']], [$data['type'], $data['status'], $data['number'], $data['payment_status'], $data['actions']]);
        $this->assertSame(['295.00', '0.00', '40.50', '335.50', '295.00'], [$data['subtotal'], $data['discount_total'], $data['tax_total'], $data['grand_total'], $data['net_total']]);

        // The customer's 30 days.
        $this->assertSame(['2026-11-27', 30, '1.00000000'], [$data['due_date'], $data['payment_terms'], $data['exchange_rate']]);

        $this->assertSame(
            [['30.00', '270.00', '40.50', ['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15.0000', 'treatment' => 'standard']], ['0.00', '25.00', '0.00', null]],
            array_map(fn (array $line) => [$line['discount_amount'], $line['amount'], $line['tax_amount'], $line['tax']], $data['lines']),
        );
        $this->assertSame([['270.00', '40.50']], array_map(fn (array $tax) => [$tax['taxable_amount'], $tax['tax_amount']], $data['taxes']));
        $this->assertSame(['Uddin Traders', null], [$data['customer']['name'], $data['business']]);

        $this->assertSame(['Added draft invoice for "Uddin Traders"'], ActivityLog::where('user_id', $this->user->id)->pluck('description')->all());
    }

    public function test_another_currency_needs_its_rate_and_the_base_total_follows_it(): void
    {
        $this->businessOf($this->user)->forceFill(['additional_currencies' => ['EUR']])->save();

        $this->postJson('/api/v1/sales/invoices', $this->invoice(['currency' => 'eur']))
            ->assertJsonValidationErrors(['exchange_rate' => 'A document in another currency needs its exchange rate.']);

        $this->postJson('/api/v1/sales/invoices', $this->invoice(['currency' => 'EUR', 'exchange_rate' => '1.0825']))
            ->assertCreated()
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.base_grand_total', '363.18');
    }

    public function test_a_document_is_checked_before_it_is_kept(): void
    {
        $business = $this->businessOf($this->user);
        $supplier = $business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Paper Mill']);
        $bought   = $business->items()->create(['type' => 'physical', 'usage' => 'purchase', 'name' => 'Printer Paper', 'unit' => 'Box']);
        $retired  = $business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Old Desk', 'unit' => 'Piece', 'is_active' => false]);
        $input    = $business->taxRates()->create(['name' => 'Input VAT', 'code' => 'IN15', 'rate' => '15', 'treatment' => 'standard', 'for_purchases' => true, 'effective_from' => '2026-01-01']);
        $line     = fn (array $fields) => ['lines' => [[...$this->invoice()['lines'][0], ...$fields]]];

        $this->postJson('/api/v1/sales/invoices', $this->invoice(['contact_id' => $supplier->id]))
            ->assertJsonValidationErrors(['contact_id' => 'Paper Mill is only a supplier. Choose a customer, or make them one on their contact page.']);
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['lines' => []]))->assertJsonValidationErrors(['lines' => 'Add at least one line.']);
        $this->postJson('/api/v1/sales/invoices', $this->invoice($line(['quantity' => '0', 'unit_price' => '-1'])))
            ->assertJsonValidationErrors(['lines.0.quantity' => 'The quantity must be more than zero.', 'lines.0.unit_price' => 'A price cannot be negative. Returns and reductions are credit notes.']);
        $this->postJson('/api/v1/sales/invoices', $this->invoice($line(['item_id' => $bought->id])))->assertJsonValidationErrors(['lines.0.item_id' => 'Printer Paper is only purchased, so it cannot be sold.']);
        $this->postJson('/api/v1/sales/invoices', $this->invoice($line(['item_id' => $retired->id])))->assertJsonValidationErrors(['lines.0.item_id' => 'Old Desk is inactive.']);
        $this->postJson('/api/v1/sales/invoices', $this->invoice($line(['tax_rate_id' => $input->id])))->assertJsonValidationErrors(['lines.0.tax_rate_id' => 'Choose one of your sales taxes.']);
        $this->postJson('/api/v1/sales/invoices', $this->invoice($line(['discount_value' => '120'])))->assertJsonValidationErrors(['lines.0.discount_value' => 'A percentage discount is at most 100%.']);
        $this->postJson('/api/v1/sales/quotations', $this->invoice())->assertJsonValidationErrors(['expiry_date' => 'Choose until when it is valid.']);

        $business->salesSetting()->create(['line_discounts' => false]);
        $this->postJson('/api/v1/sales/invoices', $this->invoice())->assertJsonValidationErrors(['lines.0.discount_value' => 'Line discounts are turned off in Settings > Sales.']);

        $this->assertSame(0, SalesDocument::count());
    }

    public function test_the_lists_filter_by_status_search_and_date_and_count_expired_quotations(): void
    {
        $lapsed = $this->draft('quotations', ['document_date' => '2026-10-20', 'expiry_date' => '2026-10-27']);
        $open   = $this->draft('quotations');
        $list   = fn (string $query = '') => array_map(fn (array $row) => [$row['id'], $row['status']], $this->getJson("/api/v1/sales/quotations{$query}")->assertOk()->json('data'));

        $this->assertSame([[$open, 'draft'], [$lapsed, 'expired']], $list());
        $this->assertSame([[$lapsed, 'expired']], $list('?status=expired'));
        $this->assertSame([[$open, 'draft']], $list('?status=draft'));
        $this->assertSame([[$open, 'draft']], $list('?date_from=2026-10-25'));
        $this->assertSame(2, count($list('?search=uddin')));
        $this->assertSame([], $list('?search=nobody'));

        $this->getJson('/api/v1/sales/quotations?status=issued')->assertJsonValidationErrors('status');
        $this->assertSame([], $this->getJson('/api/v1/sales/invoices')->json('data'));
    }

    public function test_a_document_is_found_only_as_what_it_is_and_only_in_its_business(): void
    {
        $id    = $this->draft('invoices');
        $other = $this->user->workspaces()->create(['name' => 'Second Business']);

        $this->getJson("/api/v1/sales/invoices/{$id}")->assertOk();
        $this->getJson("/api/v1/sales/quotations/{$id}")->assertNotFound()->assertJsonPath('message', 'Quotation not found.');
        $this->getJson("/api/v1/sales/invoices/{$id}", $this->pick($other))->assertNotFound();
        $this->getJson('/api/v1/sales/orders')->assertNotFound();
    }

    public function test_a_draft_is_changed_with_its_totals_worked_out_again_and_deleted(): void
    {
        $id = $this->draft('invoices');

        $this->putJson("/api/v1/sales/invoices/{$id}", $this->invoice(['lines' => [['name' => 'Consulting', 'quantity' => '3', 'unit_price' => '100.00', 'tax_rate_id' => $this->vat->id]], 'discount_type' => 'amount', 'discount_value' => '50']))
            ->assertOk()
            ->assertJsonPath('message', 'Invoice saved.')
            ->assertJsonPath('data.grand_total', '287.50')
            ->assertJsonPath('data.discount_total', '50.00');

        $this->deleteJson("/api/v1/sales/invoices/{$id}")->assertOk()->assertJsonPath('message', 'Draft invoice deleted.');

        $this->assertSame(0, SalesDocument::count());
        $this->assertSame(
            ['Added draft invoice for "Uddin Traders"', 'Updated draft invoice for "Uddin Traders"', 'Deleted draft invoice for "Uddin Traders"'],
            ActivityLog::where('user_id', $this->user->id)->orderBy('id')->pluck('description')->all(),
        );
    }

    public function test_the_form_starts_from_the_customer_and_its_totals_come_from_the_same_arithmetic(): void
    {
        $business = $this->businessOf($this->user);
        $business->forceFill(['additional_currencies' => ['EUR']])->save();
        $business->exchangeRates()->create(['currency' => 'EUR', 'base_currency' => 'USD', 'rate' => '1.08', 'effective_date' => '2026-10-01']);
        $exempt = $business->taxRates()->create(['name' => 'Exempt', 'code' => 'EX', 'rate' => '0', 'treatment' => 'exempt', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        $this->vat->forceFill(['default_for_sales' => true])->save();
        $this->customer->update(['sales_currency' => 'EUR', 'customer_payment_terms' => 15, 'tax_exemption_number' => 'EX-123']);

        $this->getJson("/api/v1/sales/invoices/defaults?contact_id={$this->customer->id}")
            ->assertOk()
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.exchange_rate', '1.08000000')
            ->assertJsonPath('data.due_date', '2026-11-12')
            ->assertJsonPath('data.payment_terms', 15)
            ->assertJsonPath('data.default_tax_id', $this->vat->id)
            ->assertJsonPath('data.customer_treatment', 'exempt')
            ->assertJsonPath('data.customer_tax_id', $exempt->id)
            ->assertJsonPath('data.customer.name', $this->customer->name);

        $this->getJson('/api/v1/sales/quotations/defaults')
            ->assertJsonPath('data.expiry_date', '2026-11-27')
            ->assertJsonPath('data.due_date', null)
            ->assertJsonPath('data.currency', 'USD');

        $this->postJson('/api/v1/sales/calculate', ['lines' => $this->invoice()['lines'] + [2 => ['quantity' => '', 'unit_price' => '']]])
            ->assertOk()
            ->assertJsonPath('data.grand_total', '335.50')
            ->assertJsonPath('data.lines.0.amount', '270.00')
            ->assertJsonPath('data.lines.2.amount', '0.00');
    }

    public function test_without_sales_lite_its_documents_are_not_there(): void
    {
        $this->businessOf($this->user)->workspaceApps()->delete();

        $this->getJson('/api/v1/sales/invoices')->assertForbidden();
        $this->postJson('/api/v1/sales/invoices', $this->invoice())->assertForbidden();
    }
}
