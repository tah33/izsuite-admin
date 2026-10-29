<?php

namespace Tests\Feature;

use App\Models\User\Item;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * What Docs 02 to 04 still asked of the shared records once Sales Lite was
 * there: a contact's documents and payments, and a sale started from their
 * profile (Doc 03 sections 9 to 11); an item's related transactions and the
 * reports that lead to them (Doc 04 sections 16, 17 and 20); a combo keeping
 * its components' warranties (section 15); delete offered only for what was
 * never used (Doc 03 section 13, Doc 04 section 18); and the Dashboard's
 * View all and recent rows (Doc 02 sections 7 and 9).
 */
class ContactAndItemHistoryTest extends TestCase
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

    public function test_a_contact_lists_its_documents_payments_and_refunds_and_is_in_use_once_on_one(): void
    {
        $this->getJson("/api/v1/contacts/{$this->customer->id}")->assertJsonPath('data.in_use', false);

        $invoice = $this->issuedInvoice();
        $this->draft('quotations');
        $other = $this->businessOf($this->user)->contacts()->create(['name' => 'Karim Stores']);
        $cash  = $this->account();

        $this->pay($invoice, $cash, '335.50')->assertCreated();
        $this->travel(1)->minutes();
        $note = $this->postJson("/api/v1/sales/invoices/{$invoice}/credit-notes", [
            'document_date' => '2026-10-28', 'reason' => 'Delivery not made',
            'lines'         => [['source_line_id' => $this->lineOf($invoice, 1), 'quantity' => '1', 'unit_price' => '25.00']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();
        $this->postJson("/api/v1/sales/credit-notes/{$note}/refunds", ['payment_date' => '2026-10-28', 'payment_account_id' => $cash->id, 'amount' => '25.00'])->assertCreated();

        $this->getJson("/api/v1/contacts/{$this->customer->id}")->assertJsonPath('data.in_use', true);
        $this->getJson('/api/v1/contacts')->assertJsonPath('data.0.name', 'Karim Stores')->assertJsonPath('data.0.in_use', false)->assertJsonPath('data.1.in_use', true);

        $this->getJson("/api/v1/documents?contact_id={$this->customer->id}&per_page=10")
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.total', 3);
        $this->getJson("/api/v1/documents?contact_id={$other->id}")->assertJsonPath('pagination.total', 0);

        $money = $this->getJson("/api/v1/sales/payments?contact_id={$this->customer->id}&kind=all")->assertOk()->json('data');
        $this->assertSame(['refund', 'payment'], array_column($money, 'kind'));

        // Payments Received stays payments only.
        $this->getJson('/api/v1/sales/payments')->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'payment');
        $this->getJson("/api/v1/sales/payments?contact_id={$other->id}&kind=all")->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/sales/payments?kind=cash')->assertUnprocessable()->assertJsonValidationErrors('kind');
    }

    public function test_a_sale_started_from_a_contact_is_made_out_to_them_while_they_can_be_sold_to(): void
    {
        $this->getJson("/api/v1/sales/quotations/defaults?contact_id={$this->customer->id}")
            ->assertJsonPath('data.customer', ['id' => $this->customer->id, 'name' => 'Uddin Traders']);

        $supplier = $this->businessOf($this->user)->contacts()->create(['name' => 'Karim Stores', 'role' => 'supplier']);
        $this->getJson("/api/v1/sales/invoices/defaults?contact_id={$supplier->id}")->assertJsonPath('data.customer', null);

        $this->customer->update(['is_active' => false]);
        $this->getJson("/api/v1/sales/invoices/defaults?contact_id={$this->customer->id}")->assertJsonPath('data.customer', null);
    }

    public function test_an_item_lists_the_lines_it_is_on_newest_first_and_is_in_use(): void
    {
        $this->getJson("/api/v1/items/{$this->chair->id}")->assertJsonPath('data.in_use', false);

        $invoice = $this->issuedInvoice();
        $this->travelTo(Carbon::parse('2026-10-29 10:00:00', 'UTC'));
        $quotation = $this->draft('quotations', ['document_date' => '2026-10-29', 'expiry_date' => '2026-11-28']);

        $rows = $this->getJson("/api/v1/items/{$this->chair->id}/transactions")->assertOk()->assertJsonPath('pagination.per_page', 10)->json('data');

        $this->assertSame([$quotation, $invoice], array_column($rows, 'document_id'));
        $this->assertSame(
            ['kind' => 'invoices', 'number' => 'INV-00001', 'contact' => 'Uddin Traders', 'status' => 'issued', 'quantity' => '2.000', 'unit' => 'Piece', 'unit_price' => '150.00', 'amount' => '270.00', 'currency' => 'USD'],
            array_intersect_key($rows[1], array_flip(['kind', 'number', 'contact', 'status', 'quantity', 'unit', 'unit_price', 'amount', 'currency'])),
        );
        $this->assertSame(['quotations', null, 'draft'], [$rows[0]['kind'], $rows[0]['number'], $rows[0]['status']]);

        $this->getJson("/api/v1/items/{$this->chair->id}")->assertJsonPath('data.in_use', true);

        // Another business's item is not found.
        $theirs = $this->user->workspaces()->create(['name' => 'Second Business'])->items()->create(['type' => 'service', 'usage' => 'sales', 'name' => 'Advice', 'unit' => 'Hour']);
        $this->getJson("/api/v1/items/{$theirs->id}/transactions")->assertNotFound();
    }

    public function test_item_and_customer_report_rows_lead_to_their_profiles(): void
    {
        $this->issuedInvoice();

        $this->getJson('/api/v1/reports/sales-summary?group_by=item')
            ->assertJsonPath('data.rows.0.group', 'Office Chair')
            ->assertJsonPath('data.rows.0.link', "/items/{$this->chair->id}")
            ->assertJsonPath('data.rows.1.link', null);

        $this->getJson('/api/v1/reports/sales-summary?group_by=customer')->assertJsonPath('data.rows.0.link', "/contacts/{$this->customer->id}");
        $this->getJson('/api/v1/reports/sales-summary?group_by=month')->assertJsonPath('data.rows.0.link', null);
    }

    public function test_a_combo_keeps_the_warranties_of_its_components(): void
    {
        $business = $this->businessOf($this->user);
        $laptop   = $business->items()->create(['type' => 'physical', 'usage' => 'sales', 'name' => 'Laptop', 'unit' => 'Piece', 'sale_price' => '900.00', 'warranty_available' => true, 'warranty_period' => 12, 'warranty_unit' => 'months', 'warranty_start_rule' => 'invoice_date']);
        $mouse    = $business->items()->create(['type' => 'physical', 'usage' => 'sales', 'name' => 'Mouse', 'unit' => 'Piece', 'sale_price' => '20.00']);
        $bundle   = $business->items()->create(['type' => Item::TYPE_COMBO, 'usage' => 'sales', 'name' => 'Laptop Bundle', 'unit' => 'Piece', 'sale_price' => '899.00', 'combo_pricing' => 'fixed', 'combo_display' => 'combo_only']);
        $bundle->components()->createMany([['component_id' => $laptop->id, 'quantity' => '1', 'sort_order' => 1], ['component_id' => $mouse->id, 'quantity' => '1', 'sort_order' => 2]]);

        $id = $this->draft('invoices', ['lines' => [['item_id' => $bundle->id, 'name' => 'Laptop Bundle', 'quantity' => '1', 'unit_price' => '899.00']]]);

        $this->getJson("/api/v1/sales/invoices/{$id}")
            ->assertJsonPath('data.lines.0.components', null)
            ->assertJsonPath('data.lines.0.warranty', [['name' => 'Laptop', 'period' => 12, 'unit' => 'months', 'start_rule' => 'invoice_date', 'terms' => null]]);
    }

    public function test_the_invoice_list_shows_all_still_owing_and_recent_rows_name_their_app(): void
    {
        $owing  = $this->issuedInvoice();
        $paid   = $this->issuedInvoice();
        $this->draft('invoices');
        $this->pay($paid, $this->account(), '335.50')->assertCreated();
        $this->pay($owing, $this->businessOf($this->user)->paymentAccounts()->first(), '35.50')->assertCreated();

        $this->getJson('/api/v1/sales/invoices?payment_status=outstanding')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owing);
        $this->getJson('/api/v1/sales/credit-notes?payment_status=outstanding')->assertUnprocessable();

        $recent = $this->getJson('/api/v1/dashboard')->assertOk()->json('data.sales.recent');
        $this->assertSame(['Sales Lite'], array_values(array_unique(array_column($recent, 'source'))));
    }
}
