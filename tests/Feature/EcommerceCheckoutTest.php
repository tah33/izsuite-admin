<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceOrder;
use App\Models\User\MarketingConsent;
use App\Models\User\Workspace;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase D4: a guest's checkout (sections 19.15 to 19.20) - what the
 * cart comes to with the address, delivery and payment chosen, and the order
 * placed: numbered, with its statuses, lines and snapshots, its contact
 * found or made, its consents - once, however often it is sent.
 */
class EcommerceCheckoutTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront([
            'Ceramic Mug' => ['sale_price' => '8.00', 'sku' => 'MUG-1'],
            'E-Book'      => ['sale_price' => '4.00', 'type' => 'digital'],
        ]);
        $this->listed('Ceramic Mug', ['weight' => '0.5']);
        $this->listed('E-Book');
        $this->setUpCheckout();
    }

    public function test_the_checkout_offers_what_the_cart_and_its_address_take(): void
    {
        $token = $this->cartWith(['ceramic-mug' => 2]);

        // Before an address: pickup, and the methods for either delivery; no shipping yet.
        $this->quote($token)->assertOk()
            ->assertJsonPath('data.needs_delivery', true)
            ->assertJsonPath('data.countries', [['code' => 'BD', 'name' => 'Bangladesh']])
            ->assertJsonPath('data.shipping_methods', [])
            ->assertJsonPath('data.pickup_points.0.name', 'Dhaka')
            ->assertJsonPath('data.pickup_points.0.ready_within', 'Ready in 2 hours')
            ->assertJsonPath('data.totals.grand_total', '16.00')
            ->assertJsonPath('data.checkout', ['guests' => true, 'signed_in' => false, 'phone' => 'required', 'notes' => true, 'open' => true, 'marketing' => null])
            ->assertJsonPath('data.policies', ['terms' => null, 'privacy' => null]);

        // An address in Bangladesh: its zone's methods; Standard chosen, its price in the total.
        $this->quote($token, ['address' => ['country' => 'BD', 'city' => 'Dhaka'], 'delivery' => 'shipping', 'shipping_method_id' => $this->ids['Standard']])
            ->assertJsonPath('data.shipping_methods', [
                ['id' => $this->ids['Standard'], 'name' => 'Standard', 'kind' => 'flat', 'price' => '5.00', 'days_min' => 2, 'days_max' => 4],
                ['id' => $this->ids['Freight'], 'name' => 'Freight', 'kind' => 'quotation', 'price' => null, 'days_min' => null, 'days_max' => null],
            ])
            ->assertJsonPath('data.chosen.shipping_method_id', $this->ids['Standard'])
            ->assertJsonPath('data.totals.shipping', '5.00')
            ->assertJsonPath('data.totals.grand_total', '21.00')
            ->assertJsonPath('data.payment_methods.*.name', ['Bank Transfer', 'bKash', 'Cash on Delivery']);

        // Picked up: no shipping, and paying on pickup instead of on delivery.
        $this->quote($token, ['delivery' => 'pickup', 'pickup_point_id' => $this->ids['Dhaka'], 'billing_country' => 'BD'])
            ->assertJsonPath('data.totals.grand_total', '16.00')
            ->assertJsonPath('data.payment_methods.*.name', ['Bank Transfer', 'bKash', 'Pay at the Shop']);

        // A country the store does not sell to: nothing to ship by.
        $this->quote($token, ['address' => ['country' => 'IN'], 'delivery' => 'shipping'])->assertJsonPath('data.shipping_methods', []);

        // No cart, no checkout.
        $this->quote(str_repeat('x', 48))->assertNotFound()->assertJsonPath('message', 'Your cart has expired. Add the products again.');
    }

    public function test_a_guest_places_an_order_once(): void
    {
        $token = $this->cartWith(['ceramic-mug' => 2]);

        $placed = $this->place($token, $this->order('21.00', ['payment_reference' => ' TRX-1001 ', 'marketing' => true, 'note' => 'Ring the bell.', 'campaign' => ['source' => 'facebook', 'campaign' => 'winter']]))
            ->assertCreated()
            ->assertJsonPath('message', 'Order placed.')
            ->assertJsonPath('data.number', 'EC-00001')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.fulfilment_status', 'unfulfilled')
            ->assertJsonPath('data.customer', ['name' => 'Karim Rahman', 'email' => 'Karim@Example.test', 'phone' => '+8801700000001', 'company' => null])
            ->assertJsonPath('data.shipping_address.city', 'Dhaka')
            ->assertJsonPath('data.delivery', ['kind' => 'shipping', 'name' => 'Standard', 'method_kind' => 'flat', 'price' => '5.00', 'quoted' => false, 'days_min' => 2, 'days_max' => 4])
            ->assertJsonPath('data.payment', ['name' => 'Bank Transfer', 'type' => 'bank_transfer', 'instructions' => 'Pay into City Bank 0123456789.', 'reference' => 'TRX-1001'])
            ->assertJsonPath('data.lines', [['title' => 'Ceramic Mug', 'options' => [], 'sku' => 'MUG-1', 'quantity' => 2, 'unit_price' => '8.00', 'amount' => '16.00']])
            ->assertJsonPath('data.totals', ['subtotal' => '16.00', 'shipping' => '5.00', 'discount' => '0.00', 'tax_total' => '0.00', 'grand_total' => '21.00']);
        $this->assertSame(64, strlen($placed->json('data.key')));

        $order = EcommerceOrder::sole();
        $this->assertSame(['karim@example.test', 'storefront', ['source' => 'facebook', 'campaign' => 'winter'], 'Ring the bell.'], [$order->customer_email, $order->source, $order->campaign, $order->customer_note]);
        $this->assertSame(['Ceramic Mug', 'Shipping: Standard'], $order->lines->pluck('name')->all());
        $this->assertSame(['1.000', null], $order->lines->pluck('weight')->all());
        $this->assertSame(hash('sha256', $placed->json('data.key')), $order->access_token_hash);
        $this->assertTrue($order->consents['marketing']);

        // Its contact, made: a customer with the details and address given.
        $contact = Contact::findOrFail($order->contact_id);
        $this->assertTrue($order->contact_created);
        $this->assertSame([Contact::ROLE_CUSTOMER, 'Karim Rahman', 'Karim@Example.test', 'Dhaka', 'BD', 'Made by the online order EC-00001 on Rahman Shop.'], [$contact->role, $contact->name, $contact->email, $contact->billing_city, $contact->billing_country, $contact->notes]);

        // Marketing consent, apart from the terms: the contact's, for Promotions and Offers by e-mail, from the website form.
        $consent = MarketingConsent::sole();
        $this->assertSame([$contact->id, 'email', 'given', 'web_form', 'Online order EC-00001 on Rahman Shop'], [$consent->contact_id, $consent->channel, $consent->status, $consent->source, $consent->evidence]);
        $this->assertSame('promotions', $consent->purpose->key);

        // The cart became the order; placing it again answers with the same one.
        $this->assertSame(EcommerceCart::CONVERTED, EcommerceCart::sole()->status);
        $this->place($token, $this->order('21.00'))->assertOk()->assertJsonPath('data.number', 'EC-00001')->assertJsonPath('data.key', $placed->json('data.key'));
        $this->assertSame(1, EcommerceOrder::count());
        $this->assertTrue(ActivityLog::where('description', 'Online order EC-00001 placed on Rahman Shop by Karim Rahman')->whereNull('user_id')->exists());

        // The same e-mail's next order: the contact its first one made.
        $next = $this->cartWith(['ceramic-mug' => 1]);
        $this->place($next, $this->order('13.00', ['customer' => ['name' => 'Karim R.', 'email' => 'KARIM@example.test', 'phone' => '01700000001']]))->assertCreated()->assertJsonPath('data.number', 'EC-00002');
        $this->assertSame([$contact->id, false], [EcommerceOrder::latest('id')->first()->contact_id, EcommerceOrder::latest('id')->first()->contact_created]);
        $this->assertSame(1, Contact::where('email', 'like', 'karim@%')->count());
    }

    public function test_an_order_is_picked_up_or_has_nothing_to_deliver(): void
    {
        $token = $this->cartWith(['ceramic-mug' => 1]);
        $this->place($token, $this->order('8.00', ['delivery' => 'pickup', 'shipping_method_id' => null, 'pickup_point_id' => $this->ids['Dhaka'], 'payment_method_id' => $this->ids['Pay at the Shop']]))->assertCreated()
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.shipping_address', null)
            ->assertJsonPath('data.delivery.kind', 'pickup')
            ->assertJsonPath('data.delivery.name', 'Dhaka')
            ->assertJsonPath('data.delivery.instructions', 'Ask at the front desk.')
            ->assertJsonPath('data.totals.shipping', '0.00');

        // A digital product alone: nothing to deliver, and only the methods for either delivery.
        $ebook = $this->cartWith(['e-book' => 1]);
        $this->quote($ebook)->assertJsonPath('data.needs_delivery', false)->assertJsonPath('data.pickup_points', [])->assertJsonPath('data.payment_methods.*.name', ['Bank Transfer', 'bKash']);
        $this->place($ebook, $this->order('4.00', ['delivery' => null, 'shipping_method_id' => null]))->assertCreated()
            ->assertJsonPath('data.fulfilment_status', 'not_required')
            ->assertJsonPath('data.delivery', ['kind' => 'none'])
            ->assertJsonPath('data.shipping_address', null);
    }

    public function test_taxes_and_a_quoted_shipping_are_kept_as_shown(): void
    {
        $vat = $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT10', 'rate' => '10', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        app(TaxRateRepository::class)->setDefault($this->business, 'sales', $vat);
        $this->business->forceFill(['tax_mode' => Workspace::TAXES_ENABLED, 'price_tax_mode' => 'exclusive'])->save();
        $this->putJson('/api/v1/business-settings/ecommerce/shipping-methods/'.$this->ids['Standard'], ['shipping_zone_id' => \App\Models\User\ShippingZone::sole()->id, 'name' => 'Standard', 'kind' => 'flat', 'price' => 5, 'tax_rate_id' => $vat->id])->assertOk();

        // Shown with tax: the mugs at 17.60, the shipping at 5.50; 23.10 in all, 2.10 of it tax.
        $token = $this->cartWith(['ceramic-mug' => 2]);
        $this->place($token, $this->order('23.10'))->assertCreated()
            ->assertJsonPath('data.prices_include_tax', true)
            ->assertJsonPath('data.lines.0.unit_price', '8.80')
            ->assertJsonPath('data.lines.0.amount', '17.60')
            ->assertJsonPath('data.totals', ['subtotal' => '17.60', 'shipping' => '5.50', 'discount' => '0.00', 'tax_total' => '2.10', 'grand_total' => '23.10']);
        $this->assertSame(['8.00', '5.00'], EcommerceOrder::sole()->lines->pluck('unit_price')->all());

        // Quoted after the order: no shipping yet, and the order says so.
        $quoted = $this->cartWith(['ceramic-mug' => 1]);
        $this->place($quoted, $this->order('8.80', ['shipping_method_id' => $this->ids['Freight']]))->assertCreated()
            ->assertJsonPath('data.delivery.quoted', true)
            ->assertJsonPath('data.totals.shipping', '0.00');
    }

    public function test_a_guest_order_never_takes_a_contact_of_the_business_by_its_e_mail_alone(): void
    {
        $own = $this->business->contacts()->create(['name' => 'Karim Traders', 'email' => 'karim@example.test', 'role' => 'customer']);

        $this->place($this->cartWith(['ceramic-mug' => 1]), $this->order('13.00'))->assertCreated();

        $order = EcommerceOrder::sole();
        $this->assertNotSame($own->id, $order->contact_id);
        $this->assertTrue($order->contact_created);
        $this->assertSame(2, Contact::where('email', 'like', 'karim@example.test')->count());
    }
}
