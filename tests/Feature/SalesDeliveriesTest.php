<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\SalesDelivery;
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
 * Doc 16 Phase C: deliveries - made from a confirmed sales order within what
 * each line has left, changed while draft or ready, numbered DN- when ready,
 * delivered with who received them and a backorder of the rest, cancelled
 * or deleted; the order's delivered quantities and delivery status following
 * them; the delivery note PDF; and the order kept from being cancelled while
 * they stand. Wednesday 28 October 2026.
 */
class SalesDeliveriesTest extends TestCase
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

        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** A confirmed order of 2 chairs and 1 delivery charge - its id. */
    private function order(): int
    {
        $id = $this->draft('orders');

        $this->postJson("/api/v1/sales/orders/{$id}/confirm")->assertOk();

        return $id;
    }

    /** [chair line id, delivery line id] of order $id. */
    private function lineIds(int $id): array
    {
        return array_column($this->getJson("/api/v1/sales/orders/{$id}")->json('data.lines'), 'id');
    }

    private function deliver(int $order, array $quantities)
    {
        return $this->postJson("/api/v1/sales/orders/{$order}/deliveries", [
            'lines' => array_map(fn ($id, $quantity) => ['line_id' => $id, 'quantity' => $quantity], array_keys($quantities), $quantities),
        ]);
    }

    public function test_a_delivery_is_made_within_what_is_left_and_numbered_when_ready(): void
    {
        // Sales Lite has none.
        $this->getJson('/api/v1/sales/deliveries')->assertForbidden();

        $this->full();
        $order            = $this->order();
        [$chair, $moving] = $this->lineIds($order);

        $this->assertSame(['2.000', '1.000'], array_column($this->getJson("/api/v1/sales/orders/{$order}")->json('data.progress'), 'to_deliver'));
        $this->deliver($order, [$chair => '3'])->assertUnprocessable()->assertJsonValidationErrors(['lines' => 'Office Chair: only 2 left to deliver.']);

        $id = $this->deliver($order, [$chair => '1'])->assertCreated()
            ->assertJsonPath('message', 'Draft delivery made from sales order SO-00001.')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.order.number', 'SO-00001')
            ->assertJsonPath('data.customer', 'Uddin Traders')
            ->assertJsonPath('data.delivery_date', '2026-10-28')
            ->assertJsonPath('data.actions', ['edit', 'ready', 'deliver', 'delete'])
            ->assertJsonPath('data.lines.0.quantity', '1.000')
            ->assertJsonPath('data.available.0.left', '2.000')
            ->json('data.id');

        // Its proof of delivery is a file list of its own.
        $this->getJson("/api/v1/sales/deliveries/{$id}/attachments")->assertOk()->assertJsonPath('data', []);

        // What a draft holds is held aside on the order.
        $this->getJson("/api/v1/sales/orders/{$order}")
            ->assertJsonPath('data.progress.0.on_deliveries', '1.000')
            ->assertJsonPath('data.progress.0.to_deliver', '1.000')
            ->assertJsonPath('data.deliveries.0.id', $id);

        $this->putJson("/api/v1/sales/deliveries/{$id}", [
            'delivery_date' => '2026-10-29', 'carrier' => 'Sundarban Courier', 'tracking_number' => 'SC-77812', 'vehicle' => 'Dhaka Metro Ga-11',
            'shipping_address' => ['line1' => 'Warehouse 4, Tejgaon', 'city' => 'Dhaka', 'country' => 'BD'],
            'lines' => [['line_id' => $chair, 'quantity' => '2'], ['line_id' => $moving, 'quantity' => '0']],
        ])->assertOk()
            ->assertJsonPath('data.carrier', 'Sundarban Courier')
            ->assertJsonPath('data.shipping_address.line1', 'Warehouse 4, Tejgaon')
            ->assertJsonPath('data.ship_to.0', 'Warehouse 4, Tejgaon')
            ->assertJsonCount(1, 'data.lines')
            ->assertJsonPath('data.lines.0.quantity', '2.000');

        $this->postJson("/api/v1/sales/deliveries/{$id}/ready")->assertOk()
            ->assertJsonPath('message', 'Delivery DN-00001 is ready.')
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.actions', ['edit', 'deliver', 'cancel']);

        $this->getJson('/api/v1/sales/deliveries?status=ready')->assertOk()
            ->assertJsonPath('data.0.number', 'DN-00001')
            ->assertJsonPath('data.0.order.number', 'SO-00001')
            ->assertJsonPath('data.0.tracking_number', 'SC-77812');

        // Its own numbering; its note is drawn with the order's template.
        $this->assertContains('delivery', array_column($this->getJson('/api/v1/business-settings/numbering')->json('data'), 'type'));
        $this->assertNotContains('delivery', array_column($this->getJson('/api/v1/business-settings/templates')->json('data'), 'type'));
        $this->getJson("/api/v1/sales/deliveries/{$id}/pdf")->assertOk();
    }

    public function test_delivering_counts_on_the_order_and_a_backorder_takes_the_rest(): void
    {
        $this->full();
        $order   = $this->order();
        [$chair] = $this->lineIds($order);
        $first   = $this->deliver($order, [$chair => '1'])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/sales/deliveries/{$first}/deliver", ['recipient_name' => 'Karim Uddin', 'backorder' => true])->assertOk()
            ->assertJsonPath('message', 'Delivery DN-00001 delivered.')
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.delivered_on', '2026-10-28')
            ->assertJsonPath('data.recipient_name', 'Karim Uddin')
            ->assertJsonPath('data.actions', ['return']);

        $this->getJson("/api/v1/sales/orders/{$order}")
            ->assertJsonPath('data.delivery_status', 'partially_delivered')
            ->assertJsonPath('data.progress.0.delivered', '1.000')
            ->assertJsonCount(2, 'data.deliveries');

        // The backorder holds the rest: a chair and the delivery charge.
        $backorder = SalesDelivery::where('backorder_of', $first)->sole();
        $this->getJson("/api/v1/sales/deliveries/{$backorder->id}")->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.backorder_of.number', 'DN-00001')
            ->assertJsonPath('data.lines.0.quantity', '1.000')
            ->assertJsonPath('data.lines.1.quantity', '1.000');

        $this->postJson("/api/v1/sales/deliveries/{$backorder->id}/deliver")->assertOk()->assertJsonPath('data.number', 'DN-00002');
        $this->getJson("/api/v1/sales/orders/{$order}")->assertJsonPath('data.delivery_status', 'delivered');

        // Invoicing by delivered quantities now has them.
        $this->putJson('/api/v1/business-settings/sales', [
            'payment_terms' => 30, 'quotation_validity' => 30, 'proforma_validity' => 30, 'line_discounts' => true, 'document_discounts' => true, 'invoice_policy' => 'delivered',
        ])->assertOk();
        $this->assertSame(['2.000', '1.000'], array_column($this->getJson("/api/v1/sales/orders/{$order}")->json('data.progress'), 'left'));
    }

    public function test_cancelling_deleting_and_what_an_order_keeps_while_deliveries_stand(): void
    {
        $this->full();
        $order            = $this->order();
        [$chair, $moving] = $this->lineIds($order);

        $draft = $this->deliver($order, [$chair => '1'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/deliveries/{$draft}/cancel")->assertStatus(409);
        $this->deleteJson("/api/v1/sales/deliveries/{$draft}")->assertOk();

        $ready = $this->deliver($order, [$chair => '2'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/deliveries/{$ready}/ready")->assertOk();

        // The order stands while a delivery does, and keeps the lines on one.
        $this->postJson("/api/v1/sales/orders/{$order}/cancel")->assertStatus(409);
        $this->putJson("/api/v1/sales/orders/{$order}/amend", $this->invoice([
            'lines' => [['id' => $moving, 'name' => 'Delivery', 'quantity' => '1', 'unit_price' => '25.00']], 'reason' => 'No chairs after all.',
        ]))->assertUnprocessable()->assertJsonValidationErrors('lines');

        $this->postJson("/api/v1/sales/deliveries/{$ready}/cancel", ['reason' => 'The customer will collect.'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.status_reason', 'The customer will collect.');
        $this->postJson("/api/v1/sales/deliveries/{$ready}/deliver")->assertStatus(409);

        // A delivered one stays: the order is completed, not cancelled.
        $done = $this->deliver($order, [$chair => '2'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/deliveries/{$done}/deliver")->assertOk();
        $this->postJson("/api/v1/sales/orders/{$order}/cancel")->assertStatus(409);
        $this->postJson("/api/v1/sales/orders/{$order}/complete")->assertOk();
        $this->deliver($order, [$moving => '1'])->assertStatus(409);
    }

    public function test_deliveries_have_permissions_of_their_own(): void
    {
        $this->full();
        $this->assertContains('sales.deliveries', array_column($this->getJson('/api/v1/roles/permissions')->json('data'), 'key'));

        $order   = $this->order();
        [$chair] = $this->lineIds($order);

        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Dispatcher', 'permissions' => ['sales.orders.view', 'sales.deliveries.view', 'sales.deliveries.create', 'sales.deliveries.edit']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->assertContains('create_delivery', $this->getJson("/api/v1/sales/orders/{$order}")->json('data.actions'));
        $id = $this->deliver($order, [$chair => '1'])->assertCreated()
            // Edit only: no Mark Ready or Delivered, no Delete Draft.
            ->assertJsonPath('data.actions', ['edit'])
            ->json('data.id');

        // Marking it ready or delivered needs its own permission.
        $this->postJson("/api/v1/sales/deliveries/{$id}/deliver")->assertForbidden();
        $role->update(['permissions' => [...$role->permissions, 'sales.deliveries.deliver']]);
        $this->postJson("/api/v1/sales/deliveries/{$id}/deliver")->assertOk();
    }
}
