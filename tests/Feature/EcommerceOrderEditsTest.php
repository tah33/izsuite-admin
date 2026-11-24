<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\SalesDocument;
use App\Models\User\ShippingZone;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
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
 * Editing an online order before payment (Doc 19 section 19.21 - Phase
 * F5a), under Edit Orders: the customer's details, and the addresses and
 * delivery - the shipping re-priced at its method's price now, the totals
 * worked out again and a confirmed order's invoice changed with them - each
 * change in the timeline with what it was; and what can no longer be edited.
 */
class EcommerceOrderEditsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private const HOME = ['line1' => 'House 12, Road 5', 'city' => 'Dhaka', 'postal_code' => '1207', 'country' => 'BD'];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-24 10:00:00');
        $this->openStorefront([
            'Ceramic Mug' => ['sale_price' => '8.00'],
            'E-Book'      => ['sale_price' => '4.00', 'type' => 'digital'],
        ]);
        $this->listed('Ceramic Mug', ['availability' => CatalogProduct::LIMITED, 'stock_quantity' => '10']);
        $this->listed('E-Book');
        $this->setUpCheckout();
    }

    /** An order of two mugs ($21.00), shipped by Standard, by bank transfer - checkout's $fields over that - and the owner signed in again. */
    private function placed(array $products = ['ceramic-mug' => 2], string $total = '21.00', array $fields = []): EcommerceOrder
    {
        $this->place($this->cartWith($products), $this->order($total, $fields))->assertCreated();
        Sanctum::actingAs($this->user);

        return EcommerceOrder::query()->latest('id')->firstOrFail();
    }

    private function delivery(EcommerceOrder $order, array $fields)
    {
        return $this->putJson(self::ORDERS."/{$order->id}/delivery", ['billing' => self::HOME, 'shipping_same' => true, 'delivery' => 'shipping', 'shipping_method_id' => $this->ids['Standard'], ...$fields]);
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

    public function test_the_customer_s_details_are_corrected_with_what_they_were(): void
    {
        $order = $this->placed();
        $given = ['name' => 'Karim R. Rahman', 'email' => 'K.Rahman@Example.test', 'phone' => null, 'company' => 'Rahman Traders'];

        $this->putJson(self::ORDERS."/{$order->id}/customer", [...$given, 'email' => 'not an e-mail'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson(self::ORDERS."/{$order->id}/customer", $given)->assertOk()
            ->assertJsonPath('message', 'The customer details of order EC-00001 are saved.')
            ->assertJsonPath('data.customer', $given);
        $this->assertSame('k.rahman@example.test', $order->fresh()->customer_email);
        $this->getJson(self::ORDERS."/{$order->id}/activity")
            ->assertJsonPath('data.0.description', 'Changed the customer details of online order EC-00001')
            ->assertJsonPath('data.0.changes', [
                ['setting' => 'name', 'from' => 'Karim Rahman', 'to' => 'Karim R. Rahman'],
                ['setting' => 'e-mail', 'from' => 'Karim@Example.test', 'to' => 'K.Rahman@Example.test'],
                ['setting' => 'phone', 'from' => '+8801700000001', 'to' => null],
                ['setting' => 'company', 'from' => null, 'to' => 'Rahman Traders'],
            ]);

        // Saved unchanged: nothing more in the timeline. Later e-mails go to the new address.
        $this->putJson(self::ORDERS."/{$order->id}/customer", $given)->assertOk();
        $this->assertCount(2, $this->getJson(self::ORDERS."/{$order->id}/activity")->json('data'));
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        $this->assertSame('K.Rahman@Example.test', EcommerceOrderEmail::where('kind', EcommerceOrderEmail::ORDER_CONFIRMED)->sole()->to);
    }

    public function test_the_delivery_is_changed_the_shipping_re_priced_and_the_invoice_with_it(): void
    {
        $zone    = ShippingZone::sole()->id;
        $express = $this->postJson('/api/v1/business-settings/ecommerce/shipping-methods', ['shipping_zone_id' => $zone, 'name' => 'Express', 'kind' => 'flat', 'price' => 12, 'days_min' => 1, 'days_max' => 2])->assertCreated()->json('data.id');
        $order   = $this->placed();
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();

        // Since: Standard at $6.
        $this->putJson("/api/v1/business-settings/ecommerce/shipping-methods/{$this->ids['Standard']}", ['shipping_zone_id' => $zone, 'name' => 'Standard', 'kind' => 'flat', 'price' => 6, 'days_min' => 2, 'days_max' => 4])->assertOk();

        $this->postJson(self::ORDERS."/{$order->id}/delivery/quote", ['billing' => ['country' => 'BD'], 'delivery' => 'shipping'])->assertOk()
            ->assertJsonPath('data.needs_delivery', true)
            ->assertJsonPath('data.sells_to', true)
            ->assertJsonPath('data.methods.*.name', ['Standard', 'Freight', 'Express'])
            ->assertJsonPath('data.methods.0.price', '6.00')
            ->assertJsonPath('data.methods.1.price', null)
            ->assertJsonPath('data.methods.2.price', '12.00')
            ->assertJsonPath('data.points.0.name', 'Dhaka');
        $this->postJson(self::ORDERS."/{$order->id}/delivery/quote", ['billing' => ['country' => 'IN'], 'delivery' => 'shipping'])->assertJsonPath('data.sells_to', false)->assertJsonPath('data.methods', []);

        $moved = ['line1' => 'House 7, Road 2', 'city' => 'Dhaka', 'postal_code' => '1207', 'country' => 'BD'];
        $this->delivery($order, ['billing' => $moved, 'shipping_method_id' => $express])->assertOk()
            ->assertJsonPath('message', 'The addresses and delivery of order EC-00001 are saved.')
            ->assertJsonPath('data.delivery.name', 'Express')
            ->assertJsonPath('data.shipping_address.line1', 'House 7, Road 2')
            ->assertJsonPath('data.totals.shipping', '12.00')
            ->assertJsonPath('data.totals.grand_total', '28.00');

        $invoice = SalesDocument::sole();
        $this->assertSame(['28.00', '28.00', 'unpaid'], [(string) $invoice->grand_total, (string) $invoice->balance_due, $invoice->payment_status]);
        $this->assertSame([['Ceramic Mug', '16.00'], ['Shipping: Express', '12.00']], $invoice->lines->map(fn ($line) => [$line->name, (string) $line->amount])->all());
        $this->assertContains('House 7, Road 2', $invoice->customer_snapshot['billing_address']);
        $this->getJson(self::ORDERS."/{$order->id}/activity")
            ->assertJsonPath('data.0.description', 'Changed the addresses and delivery of online order EC-00001')
            ->assertJsonPath('data.0.changes', [
                ['setting' => 'billing_address', 'from' => 'House 12, Road 5, Dhaka, 1207, Bangladesh', 'to' => 'House 7, Road 2, Dhaka, 1207, Bangladesh'],
                ['setting' => 'shipping_address', 'from' => 'House 12, Road 5, Dhaka, 1207, Bangladesh', 'to' => 'House 7, Road 2, Dhaka, 1207, Bangladesh'],
                ['setting' => 'delivery', 'from' => 'Standard, USD 5.00', 'to' => 'Express, USD 12.00'],
                ['setting' => 'total', 'from' => 'USD 21.00', 'to' => 'USD 28.00'],
            ]);

        // Picked up instead: no shipping; quoted after the order: none yet.
        $this->delivery($order, ['delivery' => 'pickup', 'pickup_point_id' => $this->ids['Dhaka']])->assertOk()
            ->assertJsonPath('data.delivery.kind', 'pickup')
            ->assertJsonPath('data.shipping_address', null)
            ->assertJsonPath('data.totals.grand_total', '16.00');
        $this->assertSame('16.00', (string) SalesDocument::sole()->grand_total);
        $this->delivery($order, ['shipping_method_id' => $this->ids['Freight']])->assertOk()->assertJsonPath('data.delivery.quoted', true)->assertJsonPath('data.totals.shipping', '0.00');

        // Refused: a country the store does not sell to, a method not offered there.
        $this->delivery($order, ['billing' => [...self::HOME, 'country' => 'IN']])->assertUnprocessable()->assertJsonValidationErrors(['billing.country' => 'Rahman Shop does not deliver to India.']);
        $this->delivery($order, ['shipping_method_id' => 999])->assertUnprocessable()->assertJsonValidationErrors('shipping_method_id');
    }

    public function test_cash_on_delivery_is_not_picked_up_and_an_order_with_nothing_to_deliver_changes_its_billing_alone(): void
    {
        $cod = $this->placed(fields: ['payment_method_id' => $this->ids['Cash on Delivery']]);
        $this->delivery($cod, ['delivery' => 'pickup', 'pickup_point_id' => $this->ids['Dhaka']])->assertStatus(409)
            ->assertJsonPath('message', 'Cash on Delivery is not offered for this delivery or country: keep them, or cancel the order for the customer to place it again.');
        $this->assertSame(['shipping', '21.00'], [$cod->fresh()->delivery, (string) $cod->fresh()->grand_total]);

        $book = $this->placed(['e-book' => 1], '4.00', ['delivery' => null, 'shipping_method_id' => null]);
        $this->delivery($book, ['billing' => [...self::HOME, 'line1' => 'Flat 3B']])->assertOk()
            ->assertJsonPath('data.delivery.kind', 'none')
            ->assertJsonPath('data.billing_address.line1', 'Flat 3B')
            ->assertJsonPath('data.totals.grand_total', '4.00');
    }

    public function test_a_reason_given_at_length_is_cut_in_the_timeline_and_kept_whole(): void
    {
        $order  = $this->placed();
        $reason = str_repeat('The address needs checking with the courier. ', 11);

        $this->postJson(self::ORDERS."/{$order->id}/hold", ['reason' => mb_substr($reason, 0, 500)])->assertOk();

        $entry = ActivityLog::query()->where('model_type', EcommerceOrder::class)->latest('id')->firstOrFail();
        $this->assertSame(255, mb_strlen($entry->description));
        $this->assertStringEndsWith('…', $entry->description);
        $this->assertSame('Put online order EC-00001 on hold: '.trim(mb_substr($reason, 0, 500)), $entry->properties['description']);
    }

    public function test_an_order_paid_fulfilled_or_cancelled_is_not_edited(): void
    {
        $order = $this->placed();
        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '5.00', 'payment_date' => '2026-11-24'])->assertOk();
        $this->putJson(self::ORDERS."/{$order->id}/customer", ['name' => 'K', 'email' => 'k@example.test'])->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 can no longer be edited: part of it is paid.');

        $shipped = $this->placed();
        $shipped->update(['fulfilment_status' => 'shipped']);
        $this->delivery($shipped, [])->assertStatus(409)->assertJsonPath('message', 'Order EC-00002 can no longer be edited: it is being fulfilled.');

        $cancelled = $this->placed();
        $this->postJson(self::ORDERS."/{$cancelled->id}/cancel", ['reason' => 'Asked to.'])->assertOk();
        $this->putJson(self::ORDERS."/{$cancelled->id}/customer", ['name' => 'K', 'email' => 'k@example.test'])->assertStatus(409)->assertJsonPath('message', 'Order EC-00003 can no longer be edited: it is cancelled.');
    }

    public function test_editing_needs_edit_orders_at_the_order_s_location_and_confirming_does_not(): void
    {
        $order     = $this->placed();
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);

        $this->assertContains('ecommerce.orders.view', Permissions::expand(['ecommerce.orders.edit']));
        $this->assertNotContains('ecommerce.orders.edit', Permissions::expand(['ecommerce.orders.confirm']));
        $this->assertContains('ecommerce.orders.edit', Permissions::forTemplate('manager', ['sales', 'ecommerce']));

        Sanctum::actingAs($this->member(['ecommerce.orders.view', 'ecommerce.orders.confirm']));
        $this->putJson(self::ORDERS."/{$order->id}/customer", ['name' => 'K', 'email' => 'k@example.test'])->assertForbidden();
        $this->delivery($order, [])->assertForbidden();
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();

        Sanctum::actingAs($this->member(['ecommerce.orders.edit'], [$elsewhere->id]));
        $this->putJson(self::ORDERS."/{$order->id}/customer", ['name' => 'K', 'email' => 'k@example.test'])->assertNotFound();

        Sanctum::actingAs($this->member(['ecommerce.orders.edit'], [$this->store->location_id]));
        $this->putJson(self::ORDERS."/{$order->id}/customer", ['name' => 'Karim', 'email' => 'karim@example.test'])->assertOk()->assertJsonPath('data.customer.name', 'Karim');
    }
}
