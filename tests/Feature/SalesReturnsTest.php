<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase E: customer returns of an order's delivered goods, from the
 * order or a delivery - never more than was delivered, less what other
 * returns hold; submitted (RET-), authorised or rejected, received - which
 * counts on the order - and completed with a resolution, or cancelled; and a
 * draft deleted. The order has 2 chairs and a delivery charge. Wednesday 28
 * October 2026.
 */
class SalesReturnsTest extends TestCase
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
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user = $this->signIn();
        $this->setUpSales();
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** A confirmed order with both its chairs delivered: [order id, chair line id, delivery id]. */
    private function delivered(): array
    {
        $order = $this->draft('orders');
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk();

        $chairs   = $this->getJson("/api/v1/sales/orders/{$order}")->json('data.lines.0.id');
        $delivery = $this->postJson("/api/v1/sales/orders/{$order}/deliveries", ['lines' => [['line_id' => $chairs, 'quantity' => '2']]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/deliveries/{$delivery}/deliver")->assertOk();

        return [$order, $chairs, $delivery];
    }

    private function makeReturn(int $order, array $fields): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1/sales/orders/{$order}/returns", ['reason' => 'Wrong colour', 'condition' => 'unopened', ...$fields]);
    }

    public function test_a_return_is_submitted_authorised_received_and_completed(): void
    {
        [$order, $chairs, $delivery] = $this->delivered();

        $this->assertContains('create_return', $this->getJson("/api/v1/sales/orders/{$order}")->json('data.actions'));
        $this->assertSame(['return'], $this->getJson("/api/v1/sales/deliveries/{$delivery}")->json('data.actions'));

        $this->makeReturn($order, ['delivery_id' => $delivery, 'lines' => [['line_id' => $chairs, 'quantity' => '3']]])
            ->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Office Chair: only 2 delivered and not returned yet.');

        $id = $this->makeReturn($order, ['delivery_id' => $delivery, 'lines' => [['line_id' => $chairs, 'quantity' => '1']]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.delivery.number', 'DN-00001')
            ->assertJsonPath('data.actions', ['edit', 'submit', 'delete'])
            ->assertJsonPath('data.lines.0.quantity', '1.000')
            ->json('data.id');

        $this->postJson("/api/v1/sales/returns/{$id}/submit")->assertOk()->assertJsonPath('message', 'Return RET-00001 is submitted.');
        $this->postJson("/api/v1/sales/returns/{$id}/receive")->assertStatus(409);
        $this->postJson("/api/v1/sales/returns/{$id}/authorise")->assertOk()->assertJsonPath('data.actions', ['receive', 'cancel']);
        $this->postJson("/api/v1/sales/returns/{$id}/receive", ['received_on' => '2026-10-28'])->assertOk()->assertJsonPath('data.status', 'received');

        // Back, it counts on the order.
        $order = $this->getJson("/api/v1/sales/orders/{$order}")->json('data');
        $this->assertSame(['partially_returned', '1.000'], [$order['delivery_status'], $order['progress'][0]['returned']]);
        $this->assertSame([['RET-00001', 'received']], array_map(fn (array $row) => [$row['number'], $row['status']], $order['returns']));

        $this->postJson("/api/v1/sales/returns/{$id}/complete")->assertUnprocessable()->assertJsonValidationErrors('resolution');
        $this->postJson("/api/v1/sales/returns/{$id}/complete", ['resolution' => 'credit_note'])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.resolution', 'credit_note')
            ->assertJsonPath('data.actions', []);

        // One of the two is left to return.
        $this->makeReturn($order['id'], ['lines' => [['line_id' => $chairs, 'quantity' => '2']]])->assertUnprocessable();
        $this->assertSame(1, $this->getJson('/api/v1/sales/returns?status=completed')->json('pagination.total'));
    }

    public function test_a_rejected_or_cancelled_return_frees_what_it_held_and_a_draft_is_deleted(): void
    {
        [$order, $chairs] = $this->delivered();

        $draft = $this->makeReturn($order, ['lines' => [['line_id' => $chairs, 'quantity' => '2']]])->assertCreated()->json('data.id');
        $this->makeReturn($order, ['lines' => [['line_id' => $chairs, 'quantity' => '1']]])->assertUnprocessable();
        $this->deleteJson("/api/v1/sales/returns/{$draft}")->assertOk();

        $id = $this->makeReturn($order, ['lines' => [['line_id' => $chairs, 'quantity' => '2']]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/returns/{$id}/submit")->assertOk();
        $this->postJson("/api/v1/sales/returns/{$id}/reject", ['reason' => 'Outside the return window'])->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_reason', 'Outside the return window');

        $again = $this->makeReturn($order, ['lines' => [['line_id' => $chairs, 'quantity' => '2']]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/returns/{$again}/submit")->assertOk()->assertJsonPath('data.number', 'RET-00002');
        $this->postJson("/api/v1/sales/returns/{$again}/cancel", ['reason' => 'Customer kept them'])->assertOk()->assertJsonPath('data.status', 'cancelled');

        // Nothing came back: the order is as delivered.
        $this->getJson("/api/v1/sales/orders/{$order}")->assertJsonPath('data.delivery_status', 'partially_delivered');

        // An order with nothing delivered takes no return.
        $fresh = $this->draft('orders');
        $this->postJson("/api/v1/sales/orders/{$fresh}/confirm")->assertOk();
        $this->makeReturn($fresh, ['lines' => [['line_id' => $chairs, 'quantity' => '1']]])->assertStatus(409);
    }
}
