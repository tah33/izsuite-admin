<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase B2: invoicing sales orders - in parts, each invoice line linked
 * to the order's; what is left counted from issued invoices; beyond it only
 * with "Invoice Beyond the Order" and a reason, checked again on issuing; the
 * order's invoiced and credited quantities and its invoice status following
 * its invoices and their credit notes; the invoice policy, the business's or
 * an item's; and an order with invoices kept from being cancelled.
 * Wednesday 28 October 2026.
 */
class SalesOrderInvoicingTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** A confirmed order of 2 chairs and 1 delivery - its id. */
    private function order(array $fields = []): int
    {
        $id = $this->draft('orders', $fields);

        $this->postJson("/api/v1/sales/orders/{$id}/confirm")->assertOk();

        return $id;
    }

    /** [chair line id, delivery line id] of order $id. */
    private function lineIds(int $id): array
    {
        return array_column($this->getJson("/api/v1/sales/orders/{$id}")->json('data.lines'), 'id');
    }

    /** What order $id has left to invoice, by line: [chair, delivery]. */
    private function left(int $id): array
    {
        return array_column($this->getJson("/api/v1/sales/orders/{$id}")->assertOk()->json('data.progress'), 'left');
    }

    private function invoiceOrder(int $order, array $quantities, ?string $reason = null)
    {
        return $this->postJson("/api/v1/sales/orders/{$order}/invoice", [
            'lines'  => array_map(fn ($id, $quantity) => ['line_id' => $id, 'quantity' => $quantity], array_keys($quantities), $quantities),
            'reason' => $reason,
        ]);
    }

    public function test_an_order_is_invoiced_in_parts_and_its_lines_and_status_follow(): void
    {
        $order            = $this->order();
        [$chair, $moving] = $this->lineIds($order);

        $this->assertSame(['2.000', '1.000'], $this->left($order));

        $first = $this->invoiceOrder($order, [$chair => '1'])->assertCreated()
            ->assertJsonPath('message', 'Draft invoice made from sales order SO-00001.')
            ->assertJsonPath('data.type', 'invoice')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.source.number', 'SO-00001')
            ->assertJsonPath('data.payment_terms', 30)
            ->assertJsonPath('data.due_date', '2026-11-27')
            ->assertJsonCount(1, 'data.lines')
            ->assertJsonPath('data.lines.0.source_line_id', $chair)
            ->assertJsonPath('data.lines.0.quantity', '1.000')
            ->json('data.id');

        // A draft holds its quantity: the next invoice does not take it twice.
        $this->getJson("/api/v1/sales/orders/{$order}")
            ->assertJsonPath('data.progress.0.drafted', '1.000')
            ->assertJsonPath('data.progress.0.left', '1.000')
            ->assertJsonPath('data.invoice_status', 'not_invoiced');

        $this->postJson("/api/v1/sales/invoices/{$first}/issue")->assertOk();
        $this->getJson("/api/v1/sales/orders/{$order}")
            ->assertJsonPath('data.invoice_status', 'partially_invoiced')
            ->assertJsonPath('data.progress.0.invoiced', '1.000');

        $second = $this->invoiceOrder($order, [$chair => '1', $moving => '1'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/invoices/{$second}/issue")->assertOk();

        $this->getJson("/api/v1/sales/orders/{$order}")
            ->assertJsonPath('data.invoice_status', 'invoiced')
            ->assertJsonPath('data.progress.0.invoiced', '2.000')
            ->assertJsonPath('data.progress.1.invoiced', '1.000')
            ->assertJsonCount(2, 'data.conversions');
        $this->assertSame(['0.000', '0.000'], $this->left($order));

        // Invoiced, it counts as sales; the order no longer as one to invoice.
        $this->getJson('/api/v1/sales/overview?date_from=2026-10-01&date_to=2026-10-31')
            ->assertJsonPath('data.sales.count', 2)
            ->assertJsonPath('data.orders_to_invoice.count', 0);

        // Its PDF names the order.
        $this->assertStringContainsString('SO-00001', $this->app->make(\App\Services\Documents\DocumentRenderer::class)
            ->sales($this->business, \App\Models\User\SalesDocument::find($second))->html);

        // An order is never converted to an invoice whole.
        $this->postJson("/api/v1/sales/orders/{$order}/convert", ['to' => 'invoice'])->assertStatus(409);
    }

    public function test_invoicing_beyond_needs_its_permission_and_a_reason_and_issuing_checks_again(): void
    {
        $order   = $this->order();
        [$chair] = $this->lineIds($order);

        $this->invoiceOrder($order, [$chair => '3'])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $beyond = $this->invoiceOrder($order, [$chair => '3'], 'The customer took a spare.')->assertCreated()
            ->assertJsonPath('data.over_invoice_reason', 'The customer took a spare.')
            ->json('data.id');
        $this->postJson("/api/v1/sales/invoices/{$beyond}/issue")->assertOk();
        $this->getJson("/api/v1/sales/orders/{$order}")->assertJsonPath('data.progress.0.invoiced', '3.000');

        // A draft raised beyond the order in its form, without a reason, is refused on issuing.
        $other     = $this->order();
        [$second]  = $this->lineIds($other);
        $draft     = $this->invoiceOrder($other, [$second => '1'])->assertCreated()->json('data');
        $lines     = [['source_line_id' => $second, 'item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => '5', 'unit_price' => '150.00', 'tax_rate_id' => $this->vat->id]];
        $this->putJson("/api/v1/sales/invoices/{$draft['id']}", $this->invoice_fields($lines))->assertOk()
            ->assertJsonPath('data.lines.0.source_line_id', $second);
        $this->postJson("/api/v1/sales/invoices/{$draft['id']}/issue")->assertStatus(409);

        // A link to a line of another document is dropped.
        $this->putJson("/api/v1/sales/invoices/{$draft['id']}", $this->invoice_fields([[...$lines[0], 'source_line_id' => $chair, 'quantity' => '1']]))->assertOk()
            ->assertJsonPath('data.lines.0.source_line_id', null);

        // Invoicing beyond needs the permission.
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Biller', 'permissions' => ['sales.orders.view', 'sales.invoices.view', 'sales.invoices.create', 'sales.invoices.edit', 'sales.values.view', 'contacts.view', 'items.view']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->invoiceOrder($other, [$second => '9'], 'More.')->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->invoiceOrder($other, [$second => '1'])->assertCreated();
    }

    /** An invoice's fields as its form sends them, with $lines. */
    private function invoice_fields(array $lines): array
    {
        return ['contact_id' => $this->customer->id, 'document_date' => '2026-10-28', 'currency' => 'USD', 'lines' => $lines];
    }

    public function test_the_policy_credit_notes_and_cancelling_follow_the_invoices(): void
    {
        $consulting = $this->business->items()->create(['type' => 'service', 'usage' => 'both', 'name' => 'Consulting', 'sale_price' => '100.00']);
        $this->putJson('/api/v1/business-settings/sales', [
            'payment_terms' => 30, 'quotation_validity' => 30, 'proforma_validity' => 30, 'line_discounts' => true, 'document_discounts' => true, 'invoice_policy' => 'delivered',
        ])->assertOk()->assertJsonPath('data.invoice_policy', 'delivered');

        // Delivered quantities: nothing is delivered yet - but a service invoices what was ordered.
        $order = $this->order(['lines' => [
            ['item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => '2', 'unit_price' => '150.00', 'tax_rate_id' => $this->vat->id],
            ['item_id' => $consulting->id, 'name' => 'Consulting', 'quantity' => '1', 'unit_price' => '100.00'],
        ]]);
        $this->assertSame(['0.000', '1.000'], $this->left($order));

        // An item may say otherwise.
        $this->chair->update(['invoice_policy' => 'ordered']);
        $this->assertSame(['2.000', '1.000'], $this->left($order));

        [$chair] = $this->lineIds($order);
        $invoice = $this->invoiceOrder($order, [$chair => '2'])->assertCreated()->json('data');
        $this->postJson("/api/v1/sales/invoices/{$invoice['id']}/issue")->assertOk();

        // Cancelling leaves invoices as they are, so it waits for them.
        $this->postJson("/api/v1/sales/orders/{$order}/cancel", ['reason' => 'Changed their mind.'])->assertStatus(409);

        // A credit note of its invoice counts on the order's line.
        $line = $this->getJson("/api/v1/sales/invoices/{$invoice['id']}")->json('data.lines.0.id');
        $note = $this->postJson("/api/v1/sales/invoices/{$invoice['id']}/credit-notes", [
            'document_date' => '2026-10-28', 'reason' => 'One returned', 'lines' => [['source_line_id' => $line, 'quantity' => '1', 'unit_price' => '150']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();
        $this->getJson("/api/v1/sales/orders/{$order}")->assertJsonPath('data.progress.0.credited', '1.000');

        $this->postJson("/api/v1/sales/credit-notes/{$note}/void")->assertOk();
        $this->postJson("/api/v1/sales/invoices/{$invoice['id']}/void", ['reason' => 'Wrong order.'])->assertOk();

        $this->getJson("/api/v1/sales/orders/{$order}")
            ->assertJsonPath('data.invoice_status', 'not_invoiced')
            ->assertJsonPath('data.progress.0.invoiced', '0.000')
            ->assertJsonPath('data.progress.0.credited', '0.000');
        $this->postJson("/api/v1/sales/orders/{$order}/cancel", ['reason' => 'Changed their mind.'])->assertOk();
    }
}
