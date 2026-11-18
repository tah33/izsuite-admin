<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\SalesDocumentLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase B1: sales orders - made directly or from a quotation or
 * proforma, confirmed and numbered SO-, amended with a reason, held,
 * resumed, completed, cancelled and duplicated; each converted line linked to
 * the one it came from; the Overview's order figures; and on Sales Lite an
 * order only read. Wednesday 28 October 2026.
 */
class SalesOrdersTest extends TestCase
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

        // Gotenberg, which draws the PDFs, answers with a stand-in.
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** A confirmed sales order of the base lines - its id. */
    private function confirmedOrder(array $fields = []): int
    {
        $id = $this->draft('orders', $fields);

        $this->postJson("/api/v1/sales/orders/{$id}/confirm")->assertOk();

        return $id;
    }

    /** The order's lines as its form sends them back: with their ids. */
    private function linesOf(int $id): array
    {
        return array_map(fn (array $line) => [
            'id' => $line['id'], 'item_id' => $line['item_id'], 'name' => $line['name'], 'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'], 'discount_type' => $line['discount_type'], 'discount_value' => $line['discount_value'], 'tax_rate_id' => $line['tax_rate_id'],
        ], $this->getJson("/api/v1/sales/orders/{$id}")->json('data.lines'));
    }

    public function test_an_order_is_the_full_apps_made_as_a_draft_and_numbered_when_confirmed(): void
    {
        // Sales Lite makes none.
        $this->postJson('/api/v1/sales/orders', $this->invoice())->assertForbidden();

        $this->full();
        $id = $this->draft('orders');

        $this->getJson("/api/v1/sales/orders/{$id}")->assertOk()
            ->assertJsonPath('data.type', 'sales_order')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.payment_terms', 30)
            ->assertJsonPath('data.expiry_date', null)
            ->assertJsonPath('data.grand_total', '335.50')
            ->assertJsonPath('data.actions', ['edit', 'confirm', 'record_advance', 'duplicate', 'delete']);

        $this->postJson("/api/v1/sales/orders/{$id}/confirm")->assertOk()
            ->assertJsonPath('message', 'Sales order SO-00001 confirmed.')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.delivery_status', 'not_delivered')
            ->assertJsonPath('data.invoice_status', 'not_invoiced')
            ->assertJsonPath('data.customer.name', 'Uddin Traders')
            ->assertJsonPath('data.actions', ['create_invoice', 'create_deposit', 'create_delivery', 'record_advance', 'amend', 'hold', 'complete', 'create_proforma', 'duplicate', 'cancel'])
            ->assertJsonPath('data.delivery', ['email', 'share'])
            ->assertJsonPath('data.progress.0.invoiced', '0.000');

        // Confirming locks it: the draft form no longer saves it.
        $this->putJson("/api/v1/sales/orders/{$id}", $this->invoice())->assertStatus(409);

        $this->getJson('/api/v1/sales/orders?status=confirmed')->assertOk()
            ->assertJsonPath('data.0.number', 'SO-00001')
            ->assertJsonPath('data.0.delivery_status', 'not_delivered');

        // Its numbering and template show with the full app (Settings → Documents).
        $this->assertContains('sales_order', array_column($this->getJson('/api/v1/business-settings/numbering')->assertOk()->json('data'), 'type'));
        $this->getJson('/api/v1/sales/orders/'.$id.'/pdf')->assertOk();
    }

    public function test_conversions_link_each_line_and_an_order_makes_a_proforma_staying_as_it_is(): void
    {
        $quote = $this->draft('quotations');

        // Sales Lite converts to no order.
        $this->postJson("/api/v1/sales/quotations/{$quote}/convert", ['to' => 'sales_order'])->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->assertNotContains('convert_to_sales_order', $this->getJson("/api/v1/sales/quotations/{$quote}")->json('data.actions'));

        $this->full();
        $this->assertContains('convert_to_sales_order', $this->getJson("/api/v1/sales/quotations/{$quote}")->json('data.actions'));

        $quoteLines = $this->getJson("/api/v1/sales/quotations/{$quote}")->json('data.lines');
        $order      = $this->postJson("/api/v1/sales/quotations/{$quote}/convert", ['to' => 'sales_order'])->assertCreated()
            ->assertJsonPath('data.type', 'sales_order')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.payment_terms', 30)
            ->assertJsonPath('data.source.number', null)
            ->assertJsonPath('data.lines.0.source_line_id', $quoteLines[0]['id'])
            ->assertJsonPath('data.lines.1.source_line_id', $quoteLines[1]['id'])
            ->json('data.id');

        $this->getJson("/api/v1/sales/quotations/{$quote}")->assertJsonPath('data.status', 'converted');

        // Every conversion now links its lines: a quotation's to an invoice's too.
        $other = $this->draft('quotations');
        $this->postJson("/api/v1/sales/quotations/{$other}/convert", ['to' => 'invoice'])->assertCreated()
            ->assertJsonPath('data.lines.0.source_line_id', $this->getJson("/api/v1/sales/quotations/{$other}")->json('data.lines.0.id'));

        // A confirmed order makes a proforma, and stays confirmed.
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk();
        $orderLines = $this->getJson("/api/v1/sales/orders/{$order}")->json('data.lines');

        $this->postJson("/api/v1/sales/orders/{$order}/convert", ['to' => 'proforma'])->assertCreated()
            ->assertJsonPath('data.type', 'proforma')
            ->assertJsonPath('data.source.number', 'SO-00001')
            ->assertJsonPath('data.lines.0.source_line_id', $orderLines[0]['id']);

        $this->getJson("/api/v1/sales/orders/{$order}")->assertJsonPath('data.status', 'confirmed')->assertJsonCount(1, 'data.conversions');

        // ...but is invoiced only through Create Invoice, line by line (Phase B2).
        $this->postJson("/api/v1/sales/orders/{$order}/convert", ['to' => 'invoice'])->assertStatus(409);
    }

    public function test_a_confirmed_order_is_amended_with_its_permission_and_a_reason_keeping_its_lines(): void
    {
        $this->full();
        $id    = $this->confirmedOrder();
        $lines = $this->linesOf($id);

        $this->putJson("/api/v1/sales/orders/{$id}/amend", $this->invoice(['lines' => $lines]))->assertUnprocessable()->assertJsonValidationErrors('reason');

        $lines[0]['quantity'] = '3';
        $this->putJson("/api/v1/sales/orders/{$id}/amend", $this->invoice(['lines' => $lines, 'reason' => 'The customer wants a third chair.']))->assertOk()
            ->assertJsonPath('message', 'Sales order SO-00001 amended.')
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.lines.0.id', $lines[0]['id'])
            ->assertJsonPath('data.lines.0.quantity', '3.000')
            ->assertJsonPath('data.lines.1.id', $lines[1]['id']);

        $this->getJson("/api/v1/sales/orders/{$id}/revisions")->assertOk()
            ->assertJsonPath('data.0.revision', 0)
            ->assertJsonPath('data.0.reason', 'The customer wants a third chair.');

        // Its customer and currency stay.
        $other = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Karim Stores']);
        $this->putJson("/api/v1/sales/orders/{$id}/amend", $this->invoice(['contact_id' => $other->id, 'lines' => $lines, 'reason' => 'Another customer.']))
            ->assertUnprocessable()->assertJsonValidationErrors('contact_id');

        // A line keeps what was invoiced of it, and one invoiced stays.
        SalesDocumentLine::whereKey($lines[0]['id'])->update(['invoiced_quantity' => '2']);
        $lines[0]['quantity'] = '1';
        $this->putJson("/api/v1/sales/orders/{$id}/amend", $this->invoice(['lines' => $lines, 'reason' => 'Fewer.']))
            ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity' => '2 of it was delivered or invoiced already.']);
        $this->putJson("/api/v1/sales/orders/{$id}/amend", $this->invoice(['lines' => [$lines[1]], 'reason' => 'Drop the chairs.']))
            ->assertUnprocessable()->assertJsonValidationErrors('lines');

        // Edit Draft is not enough to amend.
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Order Clerk', 'permissions' => ['sales.orders.view', 'sales.orders.create', 'sales.orders.edit', 'sales.values.view', 'contacts.view', 'items.view']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->assertSame(['duplicate'], $this->getJson("/api/v1/sales/orders/{$id}")->assertOk()->json('data.actions'));
        $this->putJson("/api/v1/sales/orders/{$id}/amend", $this->invoice(['lines' => $lines, 'reason' => 'More.']))->assertForbidden();
    }

    public function test_an_order_is_held_resumed_completed_cancelled_and_duplicated(): void
    {
        $this->full();
        $id = $this->confirmedOrder();

        $this->postJson("/api/v1/sales/orders/{$id}/hold", ['reason' => 'Waiting for the deposit.'])->assertOk()
            ->assertJsonPath('data.status', 'on_hold')
            ->assertJsonPath('data.status_reason', 'Waiting for the deposit.')
            ->assertJsonPath('data.actions', ['amend', 'resume', 'record_advance', 'duplicate', 'cancel']);
        $this->postJson("/api/v1/sales/orders/{$id}/complete")->assertStatus(409);
        $this->postJson("/api/v1/sales/orders/{$id}/resume")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->postJson("/api/v1/sales/orders/{$id}/complete")->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.actions', ['duplicate']);

        $copy = $this->postJson("/api/v1/sales/orders/{$id}/duplicate")->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.source', null)
            ->assertJsonPath('data.lines.0.source_line_id', null)
            ->assertJsonPath('data.grand_total', '335.50')
            ->json('data.id');

        $this->postJson("/api/v1/sales/orders/{$copy}/confirm")->assertOk()->assertJsonPath('data.number', 'SO-00002');
        $this->postJson("/api/v1/sales/orders/{$copy}/cancel", ['reason' => 'Ordered elsewhere.'])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $draft = $this->draft('orders');
        $this->deleteJson("/api/v1/sales/orders/{$draft}")->assertOk();
    }

    public function test_service_orders_need_no_delivery_and_the_overview_counts_orders_apart_from_sales(): void
    {
        $this->full();
        $consulting = $this->business->items()->create(['type' => 'service', 'usage' => 'both', 'name' => 'Consulting', 'sale_price' => '100.00']);

        $this->confirmedOrder();
        $service = $this->confirmedOrder(['lines' => [['item_id' => $consulting->id, 'name' => 'Consulting', 'quantity' => '1', 'unit_price' => '100.00']]]);
        $this->draft('orders');

        $this->getJson("/api/v1/sales/orders/{$service}")->assertJsonPath('data.delivery_status', 'not_required');

        $this->getJson('/api/v1/sales/overview?date_from=2026-10-01&date_to=2026-10-31')->assertOk()
            ->assertJsonPath('data.confirmed_orders', ['count' => 2, 'value' => '435.50'])
            ->assertJsonPath('data.orders_to_deliver', ['count' => 1, 'value' => '335.50'])
            ->assertJsonPath('data.orders_to_invoice', ['count' => 2, 'value' => '435.50'])
            ->assertJsonPath('data.sales.count', 0);

        // A period before them: none confirmed in it, and the open ones dated after its end are not counted.
        $this->getJson('/api/v1/sales/overview?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertJsonPath('data.confirmed_orders.count', 0)
            ->assertJsonPath('data.orders_to_invoice.count', 0);
    }

    public function test_on_sales_lite_an_order_is_only_read_and_its_permissions_hide(): void
    {
        $this->assertNotContains('sales.orders', array_column($this->getJson('/api/v1/roles/permissions')->assertOk()->json('data'), 'key'));

        $this->full();
        $this->assertContains('sales.orders', array_column($this->getJson('/api/v1/roles/permissions')->assertOk()->json('data'), 'key'));
        $id = $this->confirmedOrder();

        $this->postJson('/api/v1/apps/sales/deactivate')->assertOk();

        $this->getJson("/api/v1/sales/orders/{$id}")->assertOk()
            ->assertJsonPath('data.number', 'SO-00001')
            ->assertJsonPath('data.actions', [])
            ->assertJsonPath('data.delivery', []);
        $this->getJson("/api/v1/sales/orders/{$id}/pdf")->assertOk();
        $this->postJson("/api/v1/sales/orders/{$id}/hold")->assertForbidden();
        $this->putJson("/api/v1/sales/orders/{$id}/amend", $this->invoice(['reason' => 'Late change.']))->assertForbidden();
        $this->assertNotContains('sales_order', array_column($this->getJson('/api/v1/business-settings/numbering')->assertOk()->json('data'), 'type'));
    }
}
