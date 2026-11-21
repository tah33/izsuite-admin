<?php

namespace Tests\Feature;

use App\Models\User\Contact;
use App\Models\User\EcommerceOrder;
use App\Models\User\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase D4: what an order needs before it is placed (sections 19.15
 * and 19.20) - each refused as the customer can mend it - and what an order
 * keeps: its storefront, contact, listing and items.
 */
class EcommerceCheckoutRulesTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->listed('Ceramic Mug');
        $this->setUpCheckout();
    }

    public function test_an_order_needs_its_details_delivery_payment_and_terms(): void
    {
        $token = $this->cartWith(['ceramic-mug' => 2]);

        $this->place($token, $this->order('21.00', ['accept_terms' => false]))->assertJsonValidationErrors(['accept_terms' => 'Accept the terms and the privacy policy to place your order.']);
        $this->place($token, $this->order('21.00', ['customer' => ['name' => 'Karim', 'email' => 'not-an-address']]))->assertJsonValidationErrors(['customer.email' => 'Give a valid e-mail address.']);
        $this->place($token, $this->order('21.00', ['customer' => ['name' => 'Karim', 'email' => 'karim@example.test']]))->assertJsonValidationErrors(['customer.phone' => 'Give a phone number we can reach you at.']);
        $this->place($token, $this->order('21.00', ['shipping_same' => false]))->assertJsonValidationErrors(['shipping.line1', 'shipping.city', 'shipping.country']);
        $this->place($token, $this->order('21.00', ['delivery' => null]))->assertJsonValidationErrors(['delivery' => 'Choose delivery or pickup.']);
        $this->place($token, $this->order('21.00', ['billing' => ['line1' => '5 Park Street', 'city' => 'Kolkata', 'country' => 'IN']]))->assertJsonValidationErrors(['billing.country' => 'Rahman Shop does not deliver to India.']);
        $this->place($token, $this->order('21.00', ['shipping_method_id' => 999999]))->assertJsonValidationErrors(['shipping_method_id' => 'Choose one of the delivery options offered for this address.']);
        $this->place($token, $this->order('21.00', ['delivery' => 'pickup', 'pickup_point_id' => null]))->assertJsonValidationErrors(['pickup_point_id' => 'Choose where to collect your order.']);
        $this->place($token, $this->order('21.00', ['payment_method_id' => $this->ids['Pay at the Shop']]))->assertJsonValidationErrors(['payment_method_id' => 'Choose one of the ways to pay offered for this order.']);
        $this->place($token, $this->order('21.00', ['payment_method_id' => $this->ids['bKash']]))->assertJsonValidationErrors(['payment_reference' => "Give your payment's reference (its transaction ID)."]);

        // A total other than the one reviewed: reviewed again.
        $this->place($token, $this->order('16.00'))->assertStatus(409)->assertJsonPath('message', 'The total is now USD 21.00: review your order, then place it again.');

        // A line no longer buyable: the cart reviewed again.
        $this->items['Ceramic Mug']->catalogProduct->forceFill(['availability' => 'out_of_stock'])->save();
        $this->place($token, $this->order('21.00'))->assertStatus(409)->assertJsonPath('message', 'Your cart has changed: review it, then check out again.');
        $this->assertSame(0, EcommerceOrder::count());
    }

    public function test_orders_wait_for_a_live_store_that_takes_guests(): void
    {
        $token = $this->cartWith(['ceramic-mug' => 1]);
        $settings = ['guest_checkout' => false, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'web-', 'checkout_phone' => 'optional', 'checkout_notes' => false];

        $this->putJson('/api/v1/business-settings/ecommerce', $settings)->assertOk()->assertJsonPath('data.checkout_phone', 'optional')->assertJsonPath('data.checkout_notes', false);
        $this->place($token, $this->order('13.00'))->assertForbidden()->assertJsonPath('message', 'This store takes orders from its signed-in customers only.');

        // Guests again: the phone may be left out, a note is not kept, and the order takes the prefix.
        $this->putJson('/api/v1/business-settings/ecommerce', [...$settings, 'guest_checkout' => true])->assertOk();
        $this->store->update(['status' => Storefront::MAINTENANCE]);
        $this->place($token, $this->order('13.00'))->assertNotFound()->assertJsonPath('message', 'This store is not open.');

        $this->store->update(['status' => Storefront::LIVE]);
        $this->place($token, $this->order('13.00', ['customer' => ['name' => 'Karim', 'email' => 'karim@example.test'], 'note' => 'Leave it at the door.']))->assertCreated()
            ->assertJsonPath('data.number', 'WEB-00001')
            ->assertJsonPath('data.customer.phone', null)
            ->assertJsonPath('data.note', null);
    }

    public function test_an_order_keeps_its_storefront_contact_listing_and_items(): void
    {
        $this->place($this->cartWith(['ceramic-mug' => 1]), $this->order('13.00'))->assertCreated();
        $order = EcommerceOrder::sole();

        $this->deleteJson('/api/v1/ecommerce/storefronts/'.$this->store->id)->assertStatus(409)->assertJsonPath('message', 'Rahman Shop has orders, so it cannot be deleted. Archive it instead.');
        $this->deleteJson('/api/v1/contacts/'.$order->contact_id)->assertStatus(409)->assertJsonPath('message', 'This contact has online orders, so it cannot be deleted. Deactivate it instead.');
        $this->deleteJson('/api/v1/items/'.$this->items['Ceramic Mug']->id)->assertStatus(409)->assertJsonPath('message', 'This item is on online orders, so it cannot be deleted. Deactivate it instead.');

        // Taken out of the catalog, a listing on an order is archived instead.
        $this->deleteJson('/api/v1/ecommerce/catalog/'.$this->items['Ceramic Mug']->id)->assertOk()->assertJsonPath('message', '"Ceramic Mug" is on online orders, so it was archived instead.');
        $this->assertSame('archived', $this->items['Ceramic Mug']->catalogProduct()->value('status'));
        $this->assertTrue(Contact::whereKey($order->contact_id)->exists());
    }
}
