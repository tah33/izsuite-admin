<?php

namespace Tests\Feature;

use App\Mail\StoreOrderMail;
use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\EcommercePaymentMethod;
use App\Models\User\SalesDocument;
use App\Models\User\ShippingMethod;
use App\Models\User\ShippingZone;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * An online order's products and discount edited before payment (Doc 19
 * section 19.21 - Phase F5b), under Edit Orders: products added at their
 * price now, others' quantities changed at theirs or taken off - never the
 * last - Limited stock following a confirmed order, never past what is left;
 * the shipping re-priced by the order's own method; a discount, a percentage
 * of its products or an amount, with why; the totals and a confirmed order's
 * invoice with them, its payment method still to take the total.
 */
class EcommerceOrderProductEditsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    /** The listings by name. */
    private array $listings = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-24 10:00:00');
        $this->openStorefront([
            'Ceramic Mug' => ['sale_price' => '8.00'],
            'Tea Towel'   => ['sale_price' => '3.00'],
            'E-Book'      => ['sale_price' => '4.00', 'type' => 'digital'],
            'Brass Lamp'  => ['sale_price' => '25.00'],
            'Gold Mug'    => ['sale_price' => '30.00'],
            'Oak Table'   => ['sale_price' => '400.00'],
        ]);
        $this->listings = [
            'mug'   => $this->listed('Ceramic Mug', ['availability' => CatalogProduct::LIMITED, 'stock_quantity' => '10']),
            'towel' => $this->listed('Tea Towel'),
            'book'  => $this->listed('E-Book'),
            'lamp'  => $this->listed('Brass Lamp', ['status' => 'draft']),
            'gold'  => $this->listed('Gold Mug', ['visibility' => CatalogProduct::VISIBLE_CUSTOMERS]),
            'table' => $this->listed('Oak Table', ['sales_method' => 'quotation']),
        ];
        $this->setUpCheckout();
    }

    /** An order of two mugs ($21.00), shipped by Standard, by bank transfer - checkout's $fields over that - and the owner signed in again. */
    private function placed(array $products = ['ceramic-mug' => 2], string $total = '21.00', array $fields = []): EcommerceOrder
    {
        $this->place($this->cartWith($products), $this->order($total, $fields))->assertCreated();
        Sanctum::actingAs($this->user);

        return EcommerceOrder::query()->latest('id')->firstOrFail();
    }

    private function products(EcommerceOrder $order, array $lines)
    {
        return $this->putJson(self::ORDERS."/{$order->id}/products", ['lines' => $lines]);
    }

    private function discount(EcommerceOrder $order, array $fields)
    {
        return $this->putJson(self::ORDERS."/{$order->id}/discount", $fields);
    }

    /** $order's first product line. */
    private function lineOf(EcommerceOrder $order): EcommerceOrderLine
    {
        return $order->lines()->where('kind', EcommerceOrderLine::PRODUCT)->firstOrFail();
    }

    /** One of $listing added, as the edit sends it. */
    private function adding(string $listing, int $quantity = 1): array
    {
        return ['catalog_product_id' => $this->listings[$listing]->id, 'quantity' => $quantity];
    }

    private function member(array $permissions): User
    {
        $user   = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_products_are_added_changed_and_taken_off_a_confirmed_order_its_stock_and_invoice_with_them(): void
    {
        $order = $this->placed();
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        $mug = $this->lineOf($order);
        $this->assertSame('8.000', (string) $this->listings['mug']->fresh()->stock_quantity);

        // Since: the mug sells at $9 - the order keeps its $8.
        $this->items['Ceramic Mug']->update(['sale_price' => '9.00']);

        $this->products($order, [['id' => $mug->id, 'quantity' => 3], $this->adding('towel', 2)])->assertOk()
            ->assertJsonPath('message', 'The products of order EC-00001 are saved.')
            ->assertJsonPath('data.lines.*.title', ['Ceramic Mug', 'Tea Towel'])
            ->assertJsonPath('data.lines.0.unit_price', '8.00')
            ->assertJsonPath('data.lines.0.amount', '24.00')
            ->assertJsonPath('data.lines.1.unit_price', '3.00')
            ->assertJsonPath('data.lines.1.amount', '6.00')
            ->assertJsonPath('data.lines.1.catalog_product_id', $this->listings['towel']->id)
            ->assertJsonPath('data.totals.subtotal', '30.00')
            ->assertJsonPath('data.totals.shipping', '5.00')
            ->assertJsonPath('data.totals.grand_total', '35.00');

        // One more mug counted down; the invoice changed with the order, the shipping last.
        $this->assertSame('7.000', (string) $this->listings['mug']->fresh()->stock_quantity);
        $invoice = SalesDocument::findOrFail($order->fresh()->sales_document_id);
        $this->assertSame(['35.00', '35.00'], [(string) $invoice->grand_total, (string) $invoice->balance_due]);
        $this->assertSame(['Ceramic Mug', 'Tea Towel', 'Shipping: Standard'], $invoice->lines->pluck('name')->all());

        $this->getJson(self::ORDERS."/{$order->id}/activity")
            ->assertJsonPath('data.0.description', 'Changed the products of online order EC-00001')
            ->assertJsonPath('data.0.changes', [
                ['setting' => 'Ceramic Mug', 'from' => '2 at USD 8.00', 'to' => '3 at USD 8.00'],
                ['setting' => 'Tea Towel', 'from' => 'none', 'to' => '2 at USD 3.00'],
                ['setting' => 'total', 'from' => 'USD 21.00', 'to' => 'USD 35.00'],
            ]);

        // The towel taken off and one mug left: two mugs given back.
        $this->products($order, [['id' => $mug->id, 'quantity' => 1]])->assertOk()
            ->assertJsonPath('data.lines.*.title', ['Ceramic Mug'])
            ->assertJsonPath('data.totals.grand_total', '13.00');
        $this->assertSame('9.000', (string) $this->listings['mug']->fresh()->stock_quantity);
        $this->assertSame('13.00', (string) $invoice->fresh()->grand_total);
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.changes.1', ['setting' => 'Tea Towel', 'from' => '2 at USD 3.00', 'to' => 'none']);
    }

    public function test_a_pending_order_counts_nothing_but_never_takes_more_than_is_left(): void
    {
        $order = $this->placed();
        $mug   = $this->lineOf($order);
        $this->listings['mug']->update(['stock_quantity' => '3']);

        $this->products($order, [['id' => $mug->id, 'quantity' => 4]])->assertStatus(409)
            ->assertJsonPath('message', "Only 3 of Ceramic Mug left, and the order would have 4: change what is left in the Catalog, or the order's quantity.");
        $this->products($order, [['id' => $mug->id, 'quantity' => 3]])->assertOk()->assertJsonPath('data.totals.grand_total', '29.00');
        $this->assertSame('3.000', (string) $this->listings['mug']->fresh()->stock_quantity);

        // Confirmed, all three are counted down; then one more is more than is left.
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        $this->assertSame('0.000', (string) $this->listings['mug']->fresh()->stock_quantity);
        $this->products($order, [['id' => $mug->id, 'quantity' => 4]])->assertStatus(409)
            ->assertJsonPath('message', "Only 0 of Ceramic Mug left, and the order would take 1 more: change what is left in the Catalog, or the order's quantity.");
    }

    public function test_what_is_not_added_kept_or_taken_off(): void
    {
        $order = $this->placed();
        $mug   = ['id' => $this->lineOf($order)->id, 'quantity' => 2];

        // The last product is never taken off; a line not the order's is not changed, nor one given twice.
        $this->products($order, [])->assertUnprocessable()->assertJsonValidationErrors(['lines' => 'Keep at least one product: to take them all off, cancel the order.']);
        $this->products($order, [['id' => 999999, 'quantity' => 1]])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.id' => 'That product is no longer on the order: reload the order.']);
        $this->products($order, [$mug, $mug])->assertUnprocessable()->assertJsonValidationErrors('lines.1.id');

        // What the store does not sell this customer now - a draft, one for signed-in customers, one by quotation -
        // what is on the order already, and what is out of stock.
        foreach (['lamp', 'gold', 'table'] as $listing) {
            $this->products($order, [$mug, $this->adding($listing)])->assertUnprocessable()->assertJsonValidationErrors(['lines.1.catalog_product_id' => 'Rahman Shop does not sell this product now.']);
        }

        $this->products($order, [$mug, $this->adding('mug')])->assertUnprocessable()->assertJsonValidationErrors(['lines.1.catalog_product_id' => 'Ceramic Mug is on the order already: change its quantity.']);
        $this->listings['towel']->update(['availability' => 'out_of_stock']);
        $this->products($order, [$mug, $this->adding('towel')])->assertUnprocessable()->assertJsonValidationErrors(['lines.1.catalog_product_id' => 'Tea Towel is out of stock.']);
        $this->listings['towel']->update(['availability' => CatalogProduct::AVAILABLE]);

        // More of an inactive item is not sold; as many as before are kept.
        $this->items['Ceramic Mug']->update(['is_active' => false]);
        $this->products($order, [[...$mug, 'quantity' => 3]])->assertStatus(409)->assertJsonPath('message', 'Ceramic Mug is inactive in Items: make it active, or keep its quantity.');
        $this->products($order, [[...$mug, 'quantity' => 2], $this->adding('towel')])->assertOk()->assertJsonPath('data.totals.grand_total', '24.00');
        $this->items['Ceramic Mug']->update(['is_active' => true]);

        // A shipped order keeps a product to deliver; one delivering nothing takes none.
        $this->products($order, [$this->adding('book')])->assertStatus(409)
            ->assertJsonPath('message', 'Order EC-00001 would have nothing to deliver: keep a product to deliver, or cancel the order for the customer to place it again.');
        $book = $this->placed(['e-book' => 1], '4.00', ['delivery' => null, 'shipping_method_id' => null]);
        $this->products($book, [['id' => $this->lineOf($book)->id, 'quantity' => 1], $this->adding('towel')])->assertStatus(409)
            ->assertJsonPath('message', 'Tea Towel is delivered, and order EC-00002 has nothing to deliver: the customer can order it apart.');
        $this->products($book, [['id' => $this->lineOf($book)->id, 'quantity' => 2]])->assertOk()->assertJsonPath('data.totals.grand_total', '8.00');

        // Paid in part, nothing more is changed.
        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '5.00', 'payment_date' => '2026-11-24'])->assertOk();
        $this->products($order, [$mug])->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 can no longer be edited: part of it is paid.');
        $this->discount($order, ['type' => 'percent', 'value' => 5, 'reason' => 'Goodwill'])->assertStatus(409);
    }

    public function test_the_shipping_is_re_priced_by_the_order_s_own_method(): void
    {
        $zone  = ShippingZone::sole()->id;
        $bands = $this->postJson('/api/v1/business-settings/ecommerce/shipping-methods', ['shipping_zone_id' => $zone, 'name' => 'By Quantity', 'kind' => 'quantity', 'rates' => [['from' => 0, 'price' => 5], ['from' => 4, 'price' => 9]]])->assertCreated()->json('data.id');
        $order = $this->placed(fields: ['shipping_method_id' => $bands]);

        $this->products($order, [['id' => $this->lineOf($order)->id, 'quantity' => 4]])->assertOk()
            ->assertJsonPath('data.totals.shipping', '9.00')
            ->assertJsonPath('data.delivery.price', '9.00')
            ->assertJsonPath('data.totals.grand_total', '41.00');
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.changes', [
            ['setting' => 'Ceramic Mug', 'from' => '2 at USD 8.00', 'to' => '4 at USD 8.00'],
            ['setting' => 'shipping', 'from' => 'USD 5.00', 'to' => 'USD 9.00'],
            ['setting' => 'total', 'from' => 'USD 21.00', 'to' => 'USD 41.00'],
        ]);

        // A method that would not take them refuses them: Standard for up to $30 of products since.
        $standard = $this->placed();
        ShippingMethod::findOrFail($this->ids['Standard'])->update(['max_order' => 30]);
        $this->products($standard, [['id' => $this->lineOf($standard)->id, 'quantity' => 4]])->assertStatus(409)
            ->assertJsonPath('message', "Standard would not take order EC-00002's products: change its delivery first.");

        // Quoted after the order, it stays so.
        $quoted = $this->placed(['ceramic-mug' => 1], '8.00', ['shipping_method_id' => $this->ids['Freight']]);
        $this->products($quoted, [['id' => $this->lineOf($quoted)->id, 'quantity' => 2]])->assertOk()
            ->assertJsonPath('data.delivery.quoted', true)
            ->assertJsonPath('data.totals.shipping', '0.00')
            ->assertJsonPath('data.totals.grand_total', '16.00');
    }

    public function test_a_discount_is_given_changed_and_taken_off_with_why_its_invoice_and_customer_following(): void
    {
        $key   = $this->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00'))->assertCreated()->json('data.key');
        $order = EcommerceOrder::query()->latest('id')->firstOrFail();
        Sanctum::actingAs($this->user);

        $this->discount($order, ['type' => 'percent', 'value' => 10])->assertUnprocessable()->assertJsonValidationErrors(['reason' => "Say why: it stays in the order's timeline, not in anything the customer sees."]);
        $this->discount($order, ['type' => 'percent', 'value' => 150, 'reason' => 'Too much'])->assertUnprocessable()->assertJsonValidationErrors(['value' => 'A percentage is 100 at most.']);
        $this->discount($order, ['type' => 'amount', 'value' => 20, 'reason' => 'Too much'])->assertUnprocessable()->assertJsonValidationErrors(['value' => "The order's products come to USD 16.00: give a discount of that much at most."]);

        $this->discount($order, ['type' => 'percent', 'value' => 10, 'reason' => 'A loyal customer'])->assertOk()
            ->assertJsonPath('message', 'The discount on order EC-00001 is saved.')
            ->assertJsonPath('data.discount', ['type' => 'percent', 'value' => '10.0000', 'reason' => 'A loyal customer'])
            ->assertJsonPath('data.totals.discount', '1.60')
            ->assertJsonPath('data.totals.grand_total', '19.40');
        $this->getJson(self::ORDERS."/{$order->id}/activity")
            ->assertJsonPath('data.0.description', 'Gave online order EC-00001 a discount of 10%: A loyal customer')
            ->assertJsonPath('data.0.changes', [['setting' => 'discount', 'from' => null, 'to' => '10%'], ['setting' => 'total', 'from' => 'USD 21.00', 'to' => 'USD 19.40']]);

        // The customer sees what it takes off, never why.
        $this->getJson(self::STORE."/orders/{$key}")->assertOk()->assertJsonPath('data.totals.discount', '1.60')->assertJsonPath('data.totals.grand_total', '19.40')->assertJsonMissingPath('data.discount');
        Sanctum::actingAs($this->user);

        // Confirmed: its invoice takes off the same, as an amount; the e-mail shows it, never why.
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        $invoice = SalesDocument::findOrFail($order->fresh()->sales_document_id);
        $this->assertSame(['amount', '1.6000', '1.60', '19.40'], [$invoice->discount_type, (string) $invoice->discount_value, (string) $invoice->discount_total, (string) $invoice->grand_total]);
        Mail::assertSent(StoreOrderMail::class, function (StoreOrderMail $mail) {
            $html = $mail->render();

            return $mail->mailSubject === 'Your order EC-00001 at Rahman Shop is confirmed' && str_contains($html, 'Discount') && str_contains($html, '1.60') && ! str_contains($html, 'A loyal customer');
        });

        // A percentage follows the products: 10% of three mugs.
        $this->products($order, [['id' => $this->lineOf($order)->id, 'quantity' => 3]])->assertOk()
            ->assertJsonPath('data.totals.discount', '2.40')
            ->assertJsonPath('data.totals.grand_total', '26.60');
        $this->assertSame(['2.40', '26.60'], [(string) $invoice->fresh()->discount_total, (string) $invoice->fresh()->grand_total]);

        // An amount instead; then none.
        $this->discount($order, ['type' => 'amount', 'value' => '3', 'reason' => 'Late delivery'])->assertOk()->assertJsonPath('data.totals.grand_total', '26.00');
        $this->discount($order, ['type' => null, 'reason' => 'Full price after all'])->assertOk()
            ->assertJsonPath('data.discount', null)
            ->assertJsonPath('data.totals.discount', '0.00')
            ->assertJsonPath('data.totals.grand_total', '29.00');
        $this->getJson(self::ORDERS."/{$order->id}/activity")
            ->assertJsonPath('data.0.description', 'Took the discount off online order EC-00001: Full price after all')
            ->assertJsonPath('data.1.description', 'Changed the discount of online order EC-00001 to USD 3.00: Late delivery');
        $this->assertSame([null, '0.00', '29.00'], [$invoice->fresh()->discount_type, (string) $invoice->fresh()->discount_total, (string) $invoice->fresh()->grand_total]);

        // Nothing given, nothing changed: no more in the timeline.
        $this->discount($order, ['type' => null, 'reason' => 'Still none'])->assertOk();
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Took the discount off online order EC-00001: Full price after all');
    }

    public function test_with_prices_shown_with_tax_the_lines_stay_and_the_discount_adds_up_as_shown(): void
    {
        $vat = $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT10', 'rate' => '10', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        app(TaxRateRepository::class)->setDefault($this->business, 'sales', $vat);
        $this->business->forceFill(['tax_mode' => Workspace::TAXES_ENABLED, 'price_tax_mode' => 'exclusive'])->save();
        $this->putJson('/api/v1/business-settings/ecommerce/shipping-methods/'.$this->ids['Standard'], ['shipping_zone_id' => ShippingZone::sole()->id, 'name' => 'Standard', 'kind' => 'flat', 'price' => 5, 'tax_rate_id' => $vat->id])->assertOk();
        $order = $this->placed(total: '23.10');

        // 10% of the mugs - $1.60 before tax - is $1.76 off as the store shows prices; the lines show as they did.
        $this->discount($order, ['type' => 'percent', 'value' => 10, 'reason' => 'A loyal customer'])->assertOk()
            ->assertJsonPath('data.lines.0.amount', '17.60')
            ->assertJsonPath('data.totals.subtotal', '17.60')
            ->assertJsonPath('data.totals.shipping', '5.50')
            ->assertJsonPath('data.totals.discount', '1.76')
            ->assertJsonPath('data.totals.tax_total', '1.94')
            ->assertJsonPath('data.totals.grand_total', '21.34');

        // Its invoice keeps prices as the business does: $1.60 off before tax, to the same total.
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        $invoice = SalesDocument::findOrFail($order->fresh()->sales_document_id);
        $this->assertSame(['1.60', '1.94', '21.34'], [(string) $invoice->discount_total, (string) $invoice->tax_total, (string) $invoice->grand_total]);

        // A towel added at its price now, with its tax now: 10% of $19 off, $2.09 as shown.
        $this->products($order, [['id' => $this->lineOf($order)->id, 'quantity' => 2], $this->adding('towel')])->assertOk()
            ->assertJsonPath('data.lines.1.unit_price', '3.30')
            ->assertJsonPath('data.lines.1.amount', '3.30')
            ->assertJsonPath('data.lines.1.tax', 'VAT 10%')
            ->assertJsonPath('data.totals.subtotal', '20.90')
            ->assertJsonPath('data.totals.discount', '2.09')
            ->assertJsonPath('data.totals.grand_total', '24.31');
        $this->assertSame(['1.90', '24.31'], [(string) $invoice->fresh()->discount_total, (string) $invoice->fresh()->grand_total]);
    }

    public function test_the_new_total_is_one_its_payment_method_takes(): void
    {
        $order = $this->placed(fields: ['payment_method_id' => $this->ids['Cash on Delivery']]);
        EcommercePaymentMethod::findOrFail($this->ids['Cash on Delivery'])->update(['max_order' => 30]);

        $this->products($order, [['id' => $this->lineOf($order)->id, 'quantity' => 4]])->assertStatus(409)
            ->assertJsonPath('message', 'Cash on Delivery takes orders of up to USD 30.00, and the order would come to USD 37.00: keep it as it was, or cancel the order for the customer to place it again.');
        $this->assertSame(['21.00', '2.000'], [(string) $order->fresh()->grand_total, (string) $this->lineOf($order)->quantity]);
        $this->products($order, [['id' => $this->lineOf($order)->id, 'quantity' => 3]])->assertOk()->assertJsonPath('data.totals.grand_total', '29.00');

        // Bank Transfer from $20: a discount taking the order under it is refused.
        $paid = $this->placed();
        EcommercePaymentMethod::findOrFail($this->ids['Bank Transfer'])->update(['min_order' => 20]);
        $this->discount($paid, ['type' => 'amount', 'value' => 2, 'reason' => 'Goodwill'])->assertStatus(409)
            ->assertJsonPath('message', 'Bank Transfer takes orders of USD 20.00 or more, and the order would come to USD 19.00: keep it as it was, or cancel the order for the customer to place it again.');
        $this->assertNull($paid->fresh()->discount_type);
    }

    public function test_what_the_customer_could_be_sold_is_offered_at_its_price_now(): void
    {
        $order = $this->placed();
        $this->items['Ceramic Mug']->update(['sale_price' => '9.00']);

        // Newest first: neither the draft, nor what signed-in customers alone see, nor what is sold by quotation.
        $this->getJson(self::ORDERS."/{$order->id}/products/offered")->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.products.*.title', ['E-Book', 'Tea Towel', 'Ceramic Mug'])
            ->assertJsonPath('data.products.0.ships', false)
            ->assertJsonPath('data.products.2', ['id' => $this->listings['mug']->id, 'title' => 'Ceramic Mug', 'ships' => true, 'price' => '9.00', 'out_of_stock' => false, 'left' => '10', 'variants' => []]);
        $this->getJson(self::ORDERS."/{$order->id}/products/offered?search=towel")->assertJsonPath('data.products.*.title', ['Tea Towel']);
    }

    public function test_products_and_discounts_need_edit_orders(): void
    {
        $order = $this->placed();

        Sanctum::actingAs($this->member(['ecommerce.orders.view', 'ecommerce.orders.confirm']));
        $this->getJson(self::ORDERS."/{$order->id}/products/offered")->assertForbidden();
        $this->products($order, [['id' => $this->lineOf($order)->id, 'quantity' => 3]])->assertForbidden();
        $this->discount($order, ['type' => 'percent', 'value' => 5, 'reason' => 'Goodwill'])->assertForbidden();

        Sanctum::actingAs($this->member(['ecommerce.orders.edit']));
        $this->getJson(self::ORDERS."/{$order->id}/products/offered")->assertOk();
        $this->discount($order, ['type' => 'percent', 'value' => 5, 'reason' => 'Goodwill'])->assertOk()->assertJsonPath('data.totals.discount', '0.80');
    }
}
