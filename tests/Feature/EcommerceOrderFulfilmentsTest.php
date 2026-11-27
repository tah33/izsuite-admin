<?php

namespace Tests\Feature;

use App\Mail\StoreOrderMail;
use App\Models\User\EcommerceFulfilment;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Services\Documents\DocumentFormat;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * An online order's shipments and pickup (Doc 19 section 19.27 - Phase G1),
 * under Manage Fulfilment: a confirmed order's products that ship sent in
 * parts, each with its carrier and tracking, then delivered; a pickup order
 * made ready, then collected; one corrected or cancelled before it arrives;
 * the order's fulfilment and status following - completed once delivered
 * and paid, never by the delivery alone - and its customer e-mailed and
 * shown each.
 */
class EcommerceOrderFulfilmentsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private const TODAY = '2026-11-27';

    /** Each order's own link's key, by its id: its page as its customer opens it. */
    private array $keys = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(self::TODAY.' 10:00:00');
        $this->openStorefront([
            'Ceramic Mug' => ['sale_price' => '8.00'],
            'Tea Towel'   => ['sale_price' => '3.00'],
            'E-Book'      => ['sale_price' => '4.00', 'type' => 'digital'],
        ]);
        foreach (['Ceramic Mug', 'Tea Towel', 'E-Book'] as $name) {
            $this->listed($name);
        }
        $this->setUpCheckout();
    }

    /** An order of $products for $total - two mugs and a towel, shipped by Standard, paid on delivery, to start - confirmed, unless $confirm is false. */
    private function placed(array $products = ['ceramic-mug' => 2, 'tea-towel' => 1], string $total = '24.00', array $fields = [], bool $confirm = true): EcommerceOrder
    {
        $key = $this->place($this->cartWith($products), $this->order($total, ['payment_method_id' => $this->ids['Cash on Delivery'], ...$fields]))->assertCreated()->json('data.key');
        Sanctum::actingAs($this->user);
        $order = EcommerceOrder::query()->latest('id')->firstOrFail();
        $this->keys[$order->id] = $key;

        if ($confirm) {
            $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        }

        return $order;
    }

    /** $order's line of $title. */
    private function line(EcommerceOrder $order, string $title): EcommerceOrderLine
    {
        return $order->lines()->where('title', $title)->sole();
    }

    private function ship(EcommerceOrder $order, array $quantities, array $fields = [])
    {
        return $this->postJson(self::ORDERS."/{$order->id}/shipments", [
            'lines'      => collect($quantities)->map(fn ($quantity, $title) => ['id' => $this->line($order, $title)->id, 'quantity' => $quantity])->values()->all(),
            'shipped_on' => self::TODAY, ...$fields,
        ]);
    }

    private function step(EcommerceOrder $order, EcommerceFulfilment $fulfilment, string $what, array $fields = [])
    {
        return $this->postJson(self::ORDERS."/{$order->id}/fulfilments/{$fulfilment->id}/{$what}", $fields);
    }

    /** The newest e-mail of $kind, rendered: its subject and HTML. */
    private function mailOf(string $kind): array
    {
        $mail = Mail::sent(StoreOrderMail::class, fn (StoreOrderMail $mail) => $mail->order['kind'] === $kind)->last();

        return [$mail->mailSubject, $mail->render()];
    }

    private function member(array $permissions, ?array $locations = null): User
    {
        $user   = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $member = $this->business->members()->create([
            'user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id,
            'location_access' => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
        ]);
        $member->locations()->sync($locations ?? []);

        return $user;
    }

    public function test_an_order_ships_in_parts_is_delivered_and_completes_only_once_paid(): void
    {
        $order = $this->placed();

        $this->getJson(self::ORDERS."/{$order->id}/fulfilment")->assertOk()
            ->assertJsonPath('data.delivery', 'shipping')
            ->assertJsonPath('data.today', self::TODAY)
            ->assertJsonPath('data.lines.0', ['id' => $this->line($order, 'Ceramic Mug')->id, 'title' => 'Ceramic Mug', 'options' => [], 'sku' => null, 'quantity' => '2.000', 'sent' => '0.000', 'left' => '2.000']);

        // Part of it: one mug.
        $this->ship($order, ['Ceramic Mug' => 1, 'Tea Towel' => 0], ['carrier' => ' DHL ', 'tracking_number' => '123456', 'tracking_url' => 'https://track.example/123456'])->assertOk()
            ->assertJsonPath('message', 'Shipment 1 of order EC-00001 is on its way, and the customer told.')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.fulfilment_status', 'partially_fulfilled')
            ->assertJsonPath('data.fulfilments.0.status', 'shipped')
            ->assertJsonPath('data.fulfilments.0.carrier', 'DHL')
            ->assertJsonPath('data.fulfilments.0.lines', [['title' => 'Ceramic Mug', 'quantity' => '1.000']]);

        [$subject, $html] = $this->mailOf(EcommerceOrderEmail::ORDER_SHIPPED);
        $this->assertSame('Your order EC-00001 at Rahman Shop is on its way', $subject);
        foreach (['Part of your order <strong>EC-00001</strong> is on its way - the rest follows.', 'Shipment 1, by DHL', 'Tracking number: 123456', '<a href="https://track.example/123456"', 'Track your parcel</a>', e('You pay '.(new DocumentFormat('d M Y', 'comma_dot'))->money('24.00', 'USD').' on delivery.')] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Tea Towel', $html);
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Shipped 1 × Ceramic Mug of online order EC-00001 as shipment 1 by DHL, tracking 123456');

        // The rest, then no more than is left.
        $this->ship($order, ['Ceramic Mug' => 1, 'Tea Towel' => 1])->assertOk()->assertJsonPath('data.fulfilment_status', 'shipped');
        $this->assertStringContainsString('Your order <strong>EC-00001</strong> is on its way. Here is what was sent:', $this->mailOf(EcommerceOrderEmail::ORDER_SHIPPED)[1]);
        $this->ship($order, ['Ceramic Mug' => 1])->assertUnprocessable()->assertJsonPath('errors', ['lines.0.quantity' => ['Only 0 of Ceramic Mug are left to ship.']]);

        // Delivered one by one; delivered and unpaid, it is still processing (19.27).
        [$first, $second] = EcommerceFulfilment::orderBy('sequence')->get()->all();
        $this->step($order, $first, 'complete', ['completed_on' => self::TODAY])->assertOk()
            ->assertJsonPath('message', 'Shipment 1 of order EC-00001 is delivered, and the customer told.')
            ->assertJsonPath('data.fulfilment_status', 'shipped');
        $this->assertSame('Your order EC-00001 at Rahman Shop was delivered', $this->mailOf(EcommerceOrderEmail::ORDER_DELIVERED)[0]);
        $this->step($order, $second, 'complete', ['completed_on' => self::TODAY])->assertOk()
            ->assertJsonPath('data.fulfilment_status', 'delivered')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.payment_status', 'unpaid');

        // Paid: completed.
        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '24.00', 'payment_date' => self::TODAY])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment_status', 'paid');

        // The customer's order page: both shipments, with their tracking - and nothing of the business's own.
        $this->getJson(self::STORE.'/orders/'.$this->keys[$order->id])->assertOk()->assertJsonPath('data.fulfilments', [
            ['kind' => 'shipment', 'sequence' => 1, 'status' => 'delivered', 'fulfilled_on' => self::TODAY, 'completed_on' => self::TODAY, 'carrier' => 'DHL', 'tracking_number' => '123456',
                'tracking_url' => 'https://track.example/123456', 'lines' => [['title' => 'Ceramic Mug', 'quantity' => 1]]],
            ['kind' => 'shipment', 'sequence' => 2, 'status' => 'delivered', 'fulfilled_on' => self::TODAY, 'completed_on' => self::TODAY, 'carrier' => null, 'tracking_number' => null,
                'tracking_url' => null, 'lines' => [['title' => 'Ceramic Mug', 'quantity' => 1], ['title' => 'Tea Towel', 'quantity' => 1]]],
        ]);
    }

    public function test_fulfilment_waits_for_a_confirmed_order_with_products_that_ship(): void
    {
        $pending = $this->placed(confirm: false);
        $this->getJson(self::ORDERS."/{$pending->id}/fulfilment")->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is pending: confirm it first.');
        $this->ship($pending, ['Ceramic Mug' => 1])->assertStatus(409);

        $this->postJson(self::ORDERS."/{$pending->id}/confirm")->assertOk();
        $this->postJson(self::ORDERS."/{$pending->id}/hold", ['reason' => 'Checking.'])->assertOk();
        $this->ship($pending, ['Ceramic Mug' => 1])->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is on hold: release it first.');
        $this->postJson(self::ORDERS."/{$pending->id}/release", ['reason' => 'Fine.'])->assertOk();

        // What a shipment is made of.
        $this->postJson(self::ORDERS."/{$pending->id}/pickup", ['ready_on' => self::TODAY])->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is shipped, not collected: ship its products.');
        $this->ship($pending, ['Ceramic Mug' => 0])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Ship at least one product.');
        $this->postJson(self::ORDERS."/{$pending->id}/shipments", ['lines' => [['id' => 99999, 'quantity' => 1]], 'shipped_on' => self::TODAY])->assertUnprocessable()->assertJsonValidationErrors('lines.0.id');
        $this->ship($pending, ['Ceramic Mug' => 1], ['tracking_url' => 'ftp://track.example/1'])->assertUnprocessable()->assertJsonPath('errors.tracking_url.0', 'Give the tracking link as a web address, starting https://.');
        $this->ship($pending, ['Ceramic Mug' => 1], ['shipped_on' => '2026-11-28'])->assertUnprocessable()->assertJsonValidationErrors('shipped_on');

        // Shipped: neither held, edited nor cancelled - it is returned (Phase I).
        $this->ship($pending, ['Ceramic Mug' => 1])->assertOk();
        $this->postJson(self::ORDERS."/{$pending->id}/hold", ['reason' => 'Checking.'])->assertStatus(409);
        $this->putJson(self::ORDERS."/{$pending->id}/discount", ['type' => 'amount', 'value' => '1.00', 'reason' => 'Loyal.'])->assertStatus(409)
            ->assertJsonPath('message', 'Order EC-00001 can no longer be edited: it is processing.');
        $this->postJson(self::ORDERS."/{$pending->id}/cancel", ['reason' => 'Asked to.'])->assertStatus(409)
            ->assertJsonPath('message', 'Order EC-00001 is on its way or with its customer: it is returned, not cancelled.');

        // Nothing that ships: its digital products are delivered apart (Phase G3).
        $book = $this->placed(['e-book' => 1], '4.00', ['delivery' => null, 'shipping_method_id' => null, 'payment_method_id' => $this->ids['Bank Transfer']]);
        $this->getJson(self::ORDERS."/{$book->id}/fulfilment")->assertStatus(409)
            ->assertJsonPath('message', 'Order EC-00002 has nothing to ship or collect: its digital products and services are delivered apart.');
    }

    public function test_a_pickup_order_is_made_ready_then_collected_or_cancelled_while_ready(): void
    {
        $pickup = ['delivery' => 'pickup', 'shipping_method_id' => null, 'pickup_point_id' => $this->ids['Dhaka'], 'payment_method_id' => $this->ids['Pay at the Shop']];
        $order  = $this->placed(['ceramic-mug' => 2], '16.00', $pickup);

        $this->getJson(self::ORDERS."/{$order->id}/fulfilment")->assertJsonPath('data.delivery', 'pickup')->assertJsonPath('data.place', 'Dhaka');
        $this->ship($order, ['Ceramic Mug' => 2])->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is collected, not shipped: make it ready for pickup.');

        $this->postJson(self::ORDERS."/{$order->id}/pickup", ['ready_on' => self::TODAY, 'notes' => 'Shelf B.'])->assertOk()
            ->assertJsonPath('message', 'Order EC-00001 is ready for pickup, and the customer told.')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.fulfilment_status', 'ready_for_pickup')
            ->assertJsonPath('data.fulfilments.0.kind', 'pickup')
            ->assertJsonPath('data.fulfilments.0.lines', [['title' => 'Ceramic Mug', 'quantity' => '2.000']]);
        [$subject, $html] = $this->mailOf(EcommerceOrderEmail::ORDER_READY);
        $this->assertSame('Your order EC-00001 at Rahman Shop is ready to collect', $subject);
        $this->assertStringContainsString('is ready to collect at <strong>Dhaka</strong>', $html);
        $this->assertStringContainsString('Ask at the front desk.', $html);
        $this->assertStringNotContainsString('Shelf B.', $html);
        $this->postJson(self::ORDERS."/{$order->id}/pickup", ['ready_on' => self::TODAY])->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is ready for pickup already.');

        // Ready, it may still be cancelled - its readiness with it.
        $this->postJson(self::ORDERS."/{$order->id}/cancel", ['reason' => 'Never came.'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.fulfilments.0.status', 'cancelled')
            ->assertJsonPath('data.fulfilments.0.cancel_reason', 'The order was cancelled: Never came.');

        // Another, collected: fulfilled, and completed once paid at the shop.
        $other = $this->placed(['ceramic-mug' => 1], '8.00', $pickup);
        $this->postJson(self::ORDERS."/{$other->id}/pickup", ['ready_on' => self::TODAY])->assertOk();
        $this->step($other, EcommerceFulfilment::where('ecommerce_order_id', $other->id)->sole(), 'complete', ['completed_on' => self::TODAY])->assertOk()
            ->assertJsonPath('message', 'Order EC-00002 is collected, and the customer told.')
            ->assertJsonPath('data.fulfilment_status', 'fulfilled')
            ->assertJsonPath('data.status', 'processing');
        $this->assertSame('You collected your order EC-00002 at Rahman Shop', $this->mailOf(EcommerceOrderEmail::ORDER_DELIVERED)[0]);
        $this->postJson(self::ORDERS."/{$other->id}/payments", ['amount' => '8.00', 'payment_date' => self::TODAY])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->getJson(self::ORDERS."/{$other->id}/activity")->assertJsonPath('data.1.description', 'The customer collected online order EC-00002');
    }

    public function test_a_shipment_is_corrected_or_cancelled_until_it_arrives(): void
    {
        $order = $this->placed();
        $this->ship($order, ['Ceramic Mug' => 2, 'Tea Towel' => 1], ['carrier' => 'DHL'])->assertOk();
        $shipment = EcommerceFulfilment::sole();

        $this->putJson(self::ORDERS."/{$order->id}/fulfilments/{$shipment->id}", ['carrier' => 'FedEx', 'tracking_number' => '999', 'tracking_url' => null, 'notes' => 'Two boxes.'])->assertOk()
            ->assertJsonPath('message', 'The details of shipment 1 of order EC-00001 are saved.')
            ->assertJsonPath('data.fulfilments.0.carrier', 'FedEx')
            ->assertJsonPath('data.fulfilments.0.notes', 'Two boxes.');
        $this->getJson(self::ORDERS."/{$order->id}/activity")
            ->assertJsonPath('data.0.description', 'Changed the details of shipment 1 of online order EC-00001')
            ->assertJsonPath('data.0.changes', [['setting' => 'carrier', 'from' => 'DHL', 'to' => 'FedEx'], ['setting' => 'tracking number', 'from' => null, 'to' => '999'], ['setting' => 'notes', 'from' => null, 'to' => 'Two boxes.']]);

        // Cancelled with why: its products to ship again, the order confirmed again.
        $this->step($order, $shipment, 'cancel')->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->step($order, $shipment, 'cancel', ['reason' => 'Sent to the wrong courier.'])->assertOk()
            ->assertJsonPath('message', 'Shipment 1 of order EC-00001 is cancelled.')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.fulfilment_status', 'unfulfilled');

        $this->ship($order, ['Ceramic Mug' => 2, 'Tea Towel' => 1])->assertOk()->assertJsonPath('data.fulfilments.1.sequence', 2);
        $second = EcommerceFulfilment::where('sequence', 2)->sole();
        $this->step($order, $second, 'complete', ['completed_on' => '2026-11-26'])->assertUnprocessable()->assertJsonValidationErrors('completed_on');
        $this->step($order, $second, 'complete', ['completed_on' => self::TODAY])->assertOk();

        // Arrived: neither cancelled nor delivered again.
        $this->step($order, $second, 'cancel', ['reason' => 'Oops.'])->assertStatus(409)->assertJsonPath('message', 'Shipment 2 of order EC-00001 is delivered already: it is not cancelled.');
        $this->step($order, $second, 'complete', ['completed_on' => self::TODAY])->assertStatus(409)->assertJsonPath('message', 'Shipment 2 of order EC-00001 is delivered already.');
        $this->postJson(self::ORDERS."/{$order->id}/fulfilments/99999/complete", ['completed_on' => self::TODAY])->assertNotFound()->assertJsonPath('message', 'There is no such shipment or pickup.');
    }

    public function test_fulfilment_needs_manage_fulfilment_at_the_order_s_location(): void
    {
        $order     = $this->placed();
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);

        $this->assertContains('ecommerce.orders.view', Permissions::expand(['ecommerce.fulfilment.manage']));
        $this->assertContains('ecommerce.fulfilment.manage', Permissions::forTemplate('manager', ['sales', 'ecommerce']));

        Sanctum::actingAs($this->member(['ecommerce.orders.view', 'ecommerce.orders.confirm']));
        $this->getJson(self::ORDERS."/{$order->id}/fulfilment")->assertForbidden();
        $this->ship($order, ['Ceramic Mug' => 1])->assertForbidden();

        Sanctum::actingAs($this->member(['ecommerce.fulfilment.manage'], [$elsewhere->id]));
        $this->getJson(self::ORDERS."/{$order->id}/fulfilment")->assertNotFound();

        Sanctum::actingAs($this->member(['ecommerce.fulfilment.manage'], [$this->store->location_id]));
        $this->ship($order, ['Ceramic Mug' => 2, 'Tea Towel' => 1])->assertOk()->assertJsonPath('data.fulfilment_status', 'shipped');
    }
}
