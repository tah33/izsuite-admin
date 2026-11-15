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
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase H3b: Sales Order Backlog, Orders to Deliver, Orders to Invoice
 * and Delivery Performance, on the full Sales app. Wednesday 28 October 2026;
 * each order is the base one - 2 chairs (270.00) and delivery (25.00), 295.00
 * before tax - confirmed:
 *
 *   A  expected on the 25th, sold by the owner; nothing delivered or invoiced
 *   B  expected 5 November; its chairs delivered today by DHL, and invoiced
 *   C  put on hold, with no expected day
 *   D  expected on the 20th; delivered today by DHL - 8 days late - invoiced in full and completed
 *   E  no expected day; delivered in full today, not invoiced
 */
class OrderReportsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    /** @var array<string, int> the orders' ids, by letter */
    private array $orders = [];

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
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** A confirmed order - its id. */
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

    /** $quantities of order $id's lines delivered today, by $carrier. */
    private function deliver(int $id, array $quantities, ?string $carrier = null): void
    {
        $delivery = $this->postJson("/api/v1/sales/orders/{$id}/deliveries", [
            'lines' => array_map(fn ($line, $quantity) => ['line_id' => $line, 'quantity' => $quantity], array_keys($quantities), $quantities),
        ])->assertCreated()->json('data.id');

        SalesDelivery::query()->whereKey($delivery)->update(['carrier' => $carrier]);
        $this->postJson("/api/v1/sales/deliveries/{$delivery}/ready")->assertOk();
        $this->postJson("/api/v1/sales/deliveries/{$delivery}/deliver", ['backorder' => false])->assertOk();
    }

    /** $quantities of order $id's lines invoiced, and the invoice issued. */
    private function bill(int $id, array $quantities): void
    {
        $invoice = $this->postJson("/api/v1/sales/orders/{$id}/invoice", [
            'lines' => array_map(fn ($line, $quantity) => ['line_id' => $line, 'quantity' => $quantity], array_keys($quantities), $quantities),
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/sales/invoices/{$invoice}/issue")->assertOk();
    }

    /** The story's orders. */
    private function sell(): void
    {
        $this->full();

        $this->orders['A'] = $this->order(['expected_delivery_date' => '2026-10-25', 'salesperson_id' => $this->user->id]);

        $this->orders['B'] = $this->order(['expected_delivery_date' => '2026-11-05']);
        [$chairs]          = $this->lineIds($this->orders['B']);
        $this->deliver($this->orders['B'], [$chairs => '2'], 'DHL');
        $this->bill($this->orders['B'], [$chairs => '2']);

        $this->orders['C'] = $this->order();
        $this->postJson("/api/v1/sales/orders/{$this->orders['C']}/hold")->assertOk();

        $this->orders['D'] = $this->order(['expected_delivery_date' => '2026-10-20']);
        [$chairs, $delivery] = $this->lineIds($this->orders['D']);
        $this->deliver($this->orders['D'], [$chairs => '2', $delivery => '1'], 'DHL');
        $this->bill($this->orders['D'], [$chairs => '2', $delivery => '1']);
        $this->postJson("/api/v1/sales/orders/{$this->orders['D']}/complete")->assertOk();

        $this->orders['E'] = $this->order();
        [$chairs, $delivery] = $this->lineIds($this->orders['E']);
        $this->deliver($this->orders['E'], [$chairs => '2', $delivery => '1']);

        $this->draft('orders');
    }

    /** @return list<list<mixed>> the rows of $report, each as the values of $keys */
    private function rows(string $report, array $keys): array
    {
        return array_map(
            fn (array $row) => array_values(array_intersect_key(array_replace(array_flip($keys), $row), array_flip($keys))),
            $this->getJson("/api/v1/reports/{$report}")->assertOk()->json('data.rows'),
        );
    }

    public function test_the_four_come_with_the_full_app_each_for_a_role_that_may_see_it(): void
    {
        $keys   = fn () => array_column($this->getJson('/api/v1/reports')->assertOk()->json('data'), 'key');
        $theirs = ['order-backlog', 'orders-to-deliver', 'orders-to-invoice', 'delivery-performance'];

        $this->assertSame([], array_intersect($theirs, $keys()));

        $this->full();
        $all = $keys();
        $this->assertSame($theirs, array_slice($all, array_search('expired-quotations', $all, true) + 1, 4));

        // Delivery Performance shows no values: deliveries' View is enough. The others need sales and orders seen.
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Dispatch', 'permissions' => ['reports.view', 'sales.deliveries.view']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->assertSame(['delivery-performance'], array_values(array_intersect($keys(), $theirs)));
        $this->getJson('/api/v1/reports/order-backlog')->assertNotFound();

        $role->forceFill(['permissions' => [...$role->permissions, 'sales.invoices.view', 'sales.values.view', 'sales.orders.view']])->save();
        $this->assertSame($theirs, array_values(array_intersect($keys(), $theirs)));
    }

    public function test_the_backlog_is_what_open_orders_have_left(): void
    {
        $this->sell();

        $keys = ['group', 'orders', 'value', 'to_deliver', 'to_invoice'];

        // D is completed and the draft not confirmed: A, B, C (held) and E count.
        $this->assertSame([['Oct 2026', 4, '1180.00', '615.00', '910.00']], $this->rows('order-backlog', $keys));
        $this->assertSame([
            ['No salesperson', 3, '885.00', '320.00', '615.00'],
            [$this->user->name, 1, '295.00', '295.00', '295.00'],
        ], $this->rows('order-backlog?group_by=salesperson', $keys));

        $this->getJson('/api/v1/reports/order-backlog')->assertOk()
            ->assertJsonPath('data.period', 'Open on 28 Oct 2026')
            ->assertJsonPath('data.columns.0.label', 'Month Ordered')
            ->assertJsonPath('data.totals', ['group' => 'Total', 'orders' => 4, 'value' => '1180.00', 'to_deliver' => '615.00', 'to_invoice' => '910.00']);
    }

    public function test_orders_to_deliver_put_the_latest_due_first(): void
    {
        $this->sell();

        // E is delivered in full; B has its delivery charge left. Those without an expected day come last.
        $this->assertSame([
            ['2026-10-25', 3, 'Confirmed', 'Not Delivered', '295.00', "/sales/orders/{$this->orders['A']}"],
            ['2026-11-05', 0, 'Confirmed', 'Partially Delivered', '25.00', "/sales/orders/{$this->orders['B']}"],
            [null, 0, 'On Hold', 'Not Delivered', '295.00', "/sales/orders/{$this->orders['C']}"],
        ], $this->rows('orders-to-deliver', ['expected_delivery_date', 'days_late', 'status', 'delivery_status', 'to_deliver', 'link']));

        $this->getJson('/api/v1/reports/orders-to-deliver')->assertJsonPath('data.totals', ['number' => 'Total', 'to_deliver' => '615.00']);
    }

    public function test_orders_to_invoice_show_what_is_left_to_invoice(): void
    {
        $this->sell();

        $this->assertSame([
            ['Not Invoiced', '295.00', '0.00', '295.00', "/sales/orders/{$this->orders['A']}"],
            ['Partially Invoiced', '295.00', '270.00', '25.00', "/sales/orders/{$this->orders['B']}"],
            ['Not Invoiced', '295.00', '0.00', '295.00', "/sales/orders/{$this->orders['C']}"],
            ['Not Invoiced', '295.00', '0.00', '295.00', "/sales/orders/{$this->orders['E']}"],
        ], $this->rows('orders-to-invoice', ['invoice_status', 'value', 'invoiced', 'to_invoice', 'link']));

        $this->getJson('/api/v1/reports/orders-to-invoice')->assertJsonPath('data.totals', ['number' => 'Total', 'value' => '1180.00', 'invoiced' => '270.00', 'to_invoice' => '910.00']);
    }

    public function test_delivery_performance_weighs_each_delivery_against_its_expected_day(): void
    {
        $this->sell();

        $keys = ['group', 'deliveries', 'on_time', 'late', 'unplanned', 'rate', 'average_late'];

        $this->assertSame([['Oct 2026', 3, 1, 1, 1, '50.0', '8.0']], $this->rows('delivery-performance', $keys));
        $this->assertSame([
            ['DHL', 2, 1, 1, 0, '50.0', '8.0'],
            ['No carrier', 1, 0, 0, 1, null, null],
        ], $this->rows('delivery-performance?group_by=carrier', $keys));

        $this->getJson('/api/v1/reports/delivery-performance')
            ->assertJsonPath('data.totals', ['group' => 'Total', 'deliveries' => 3, 'on_time' => 1, 'late' => 1, 'unplanned' => 1, 'rate' => '50.0', 'average_late' => '8.0']);
    }
}
