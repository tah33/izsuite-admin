<?php

namespace Tests\Feature;

use App\Models\User\EcommerceFulfilment;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Services\Documents\DocumentFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A shipment's packing slip (Doc 19 sections 19.27 and 19.39 - Phase G2): a
 * PDF of what the parcel holds and where it goes, with its carrier and
 * tracking - never a price, nor the business's notes - in the invoice's
 * template, for whoever may view the order at its location. A pickup has
 * none.
 */
class EcommercePackingSlipsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private const TODAY = '2026-11-27';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->travelTo(self::TODAY.' 10:00:00');
        $this->openStorefront([
            'Ceramic Mug' => ['sale_price' => '8.00', 'sku' => 'MUG-01'],
            'Tea Towel'   => ['sale_price' => '3.00'],
        ]);
        foreach (['Ceramic Mug', 'Tea Towel'] as $name) {
            $this->listed($name);
        }
        $this->setUpCheckout();
    }

    /** A confirmed order of $products for $total - two mugs and a towel, shipped by Standard and paid on delivery, to start. */
    private function placed(array $products = ['ceramic-mug' => 2, 'tea-towel' => 1], string $total = '24.00', array $fields = []): EcommerceOrder
    {
        $this->place($this->cartWith($products), $this->order($total, ['payment_method_id' => $this->ids['Cash on Delivery'], ...$fields]))->assertCreated();
        Sanctum::actingAs($this->user);
        $order = EcommerceOrder::query()->latest('id')->firstOrFail();
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();

        return $order;
    }

    private function ship(EcommerceOrder $order, array $quantities, array $fields = []): EcommerceFulfilment
    {
        $this->postJson(self::ORDERS."/{$order->id}/shipments", [
            'lines'      => collect($quantities)->map(fn ($quantity, $title) => ['id' => EcommerceOrderLine::where('ecommerce_order_id', $order->id)->where('title', $title)->value('id'), 'quantity' => $quantity])->values()->all(),
            'shipped_on' => self::TODAY, ...$fields,
        ])->assertOk();

        return EcommerceFulfilment::query()->latest('id')->firstOrFail();
    }

    private function slip(EcommerceOrder $order, EcommerceFulfilment $shipment, string $query = '')
    {
        return $this->get(self::ORDERS."/{$order->id}/fulfilments/{$shipment->id}/pdf{$query}", ['Accept' => 'application/json']);
    }

    /** The HTML of the last PDF asked of Gotenberg. */
    private function sentHtml(): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();

        return (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
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

    public function test_a_packing_slip_shows_what_the_parcel_holds_and_where_it_goes_without_prices_or_notes(): void
    {
        $order = $this->placed();
        // A variant's options, as the store showed them (Phase B3).
        EcommerceOrderLine::where('ecommerce_order_id', $order->id)->where('title', 'Ceramic Mug')->sole()->update(['options' => [['name' => 'Colour', 'value' => 'Blue']]]);
        $first = $this->ship($order, ['Ceramic Mug' => 1, 'Tea Towel' => 0], [
            'carrier' => 'Pathao', 'tracking_number' => 'PT-1001', 'tracking_url' => 'https://track.example/PT-1001', 'notes' => 'Fragile: wrap it twice.',
        ]);

        $this->slip($order, $first, '?download=1')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=Packing-Slip-EC-00001-Shipment-1.pdf');

        $html = $this->sentHtml();
        $this->assertStringContainsString('<title>Packing Slip EC-00001 Shipment 1</title>', $html);
        foreach (['Packing Slip', 'EC-00001', 'Karim Rahman', 'Ship To', 'House 12, Road 5', 'Shipment', 'Shipped On',
            'Order Date', 'Shipping Method', 'Standard', 'Pathao', 'Tracking No.', 'PT-1001', 'Ceramic Mug', 'Colour: Blue', 'MUG-01', 'Piece', 'This parcel holds part of order EC-00001.', 'Received By'] as $text) {
            $this->assertStringContainsString(e($text), $html);
        }
        $money = new DocumentFormat('d M Y', 'comma_dot');
        foreach (['Tea Towel', 'Fragile', $money->money('8.00', 'USD'), $money->money('24.00', 'USD'), 'Delivered On'] as $text) {
            $this->assertStringNotContainsString(e($text), $html);
        }
        $this->assertStringNotContainsString('class="stamp ', $html);

        // The rest: part of the order too, as one mug went before.
        $second = $this->ship($order, ['Ceramic Mug' => 1, 'Tea Towel' => 1]);
        $this->slip($order, $second)->assertOk()->assertHeader('Content-Disposition', 'inline; filename=Packing-Slip-EC-00001-Shipment-2.pdf');
        $this->assertStringContainsString('Tea Towel', $html = $this->sentHtml());
        $this->assertStringContainsString('This parcel holds part of order EC-00001.', $html);

        // Delivered, it says when; cancelled, it says so across it.
        $this->postJson(self::ORDERS."/{$order->id}/fulfilments/{$second->id}/complete", ['completed_on' => self::TODAY])->assertOk();
        $this->slip($order, $second)->assertOk();
        $this->assertStringContainsString('Delivered On', $this->sentHtml());
        $this->postJson(self::ORDERS."/{$order->id}/fulfilments/{$first->id}/cancel", ['reason' => 'Sent to the wrong courier.'])->assertOk();
        $this->slip($order, $first)->assertOk();
        $this->assertStringContainsString('class="stamp stamp--cancelled">Cancelled</div>', $html = $this->sentHtml());
        $this->assertStringNotContainsString('wrong courier', $html);

        // All of an order in one parcel: not a part.
        $whole = $this->placed(['tea-towel' => 2], '11.00');
        $this->slip($whole, $this->ship($whole, ['Tea Towel' => 2]))->assertOk();
        $this->assertStringNotContainsString('This parcel holds part', $html = $this->sentHtml());
        $this->assertStringNotContainsString('MUG-01', $html);
    }

    public function test_a_packing_slip_is_a_shipment_s_for_whoever_may_view_the_order_at_its_location(): void
    {
        $order     = $this->placed();
        $shipment  = $this->ship($order, ['Ceramic Mug' => 2, 'Tea Towel' => 1]);
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);

        Sanctum::actingAs($this->member(['ecommerce.orders.view'], [$this->store->location_id]));
        $this->slip($order, $shipment)->assertOk();

        Sanctum::actingAs($this->member(['ecommerce.store.view']));
        $this->slip($order, $shipment)->assertForbidden();

        Sanctum::actingAs($this->member(['ecommerce.orders.view'], [$elsewhere->id]));
        $this->slip($order, $shipment)->assertNotFound();

        // A pickup has none, nor is another order's shipment this one's.
        Sanctum::actingAs($this->user);
        $pickup = $this->placed(['ceramic-mug' => 1], '8.00', ['delivery' => 'pickup', 'shipping_method_id' => null, 'pickup_point_id' => $this->ids['Dhaka'], 'payment_method_id' => $this->ids['Pay at the Shop']]);
        $this->postJson(self::ORDERS."/{$pickup->id}/pickup", ['ready_on' => self::TODAY])->assertOk();
        $ready = EcommerceFulfilment::query()->where('kind', EcommerceFulfilment::PICKUP)->sole();
        $this->slip($pickup, $ready)->assertNotFound()->assertJsonPath('message', 'There is no such shipment.');
        $this->slip($pickup, $shipment)->assertNotFound();
    }
}
