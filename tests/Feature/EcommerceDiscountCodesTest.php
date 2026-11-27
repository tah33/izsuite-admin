<?php

namespace Tests\Feature;

use App\Mail\StoreOrderMail;
use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceOrder;
use App\Services\Documents\DocumentFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A discount code at checkout (Doc 19 section 19.31 - Phase H1): checked by
 * the backend at each quote and as the order is placed, refused saying why;
 * taking off a percentage, an amount or the shipping, on the whole order or
 * chosen products; its order keeping it as it was, as its discount - and its
 * invoice the same - its uses counted, a cancelled order's given back.
 */
class EcommerceDiscountCodesTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const DISCOUNTS = '/api/v1/ecommerce/discounts';

    private const ORDERS = '/api/v1/ecommerce/orders';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-27 10:00:00');
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00'], 'Tea Towel' => ['sale_price' => '3.00']]);
        $this->listed('Ceramic Mug');
        $this->listed('Tea Towel');
        $this->setUpCheckout();
    }

    /** A discount of $fields over a 10% one on the whole order, SAVE10 - its id. */
    private function discount(array $fields = []): int
    {
        Sanctum::actingAs($this->user);

        return $this->postJson(self::DISCOUNTS, ['name' => 'Summer Sale', 'code' => 'SAVE10', 'kind' => 'percentage', 'value' => 10, 'applies_to' => 'order', ...$fields])->assertCreated()->json('data.id');
    }

    /** Two mugs ($16) and a towel ($3), shipped by Standard ($5), quoted with $code - for $email. */
    private function quoted(?string $code, string $email = 'karim@example.test', array $products = ['ceramic-mug' => 2, 'tea-towel' => 1])
    {
        return $this->quote($this->freshCart($products), [
            'address' => ['country' => 'BD'], 'billing_country' => 'BD', 'delivery' => 'shipping', 'shipping_method_id' => $this->ids['Standard'],
            'payment_method_id' => $this->ids['Bank Transfer'], 'discount_code' => $code, 'email' => $email,
        ])->assertOk();
    }

    /** A new cart of $products - the token of a cart before it let go, as test headers outlast their request. */
    private function freshCart(array $products): string
    {
        $this->flushHeaders();

        return $this->cartWith($products);
    }

    private function money(string $amount): string
    {
        return (new DocumentFormat('d M Y', 'comma_dot'))->money($amount, 'USD');
    }

    public function test_a_code_takes_off_and_its_order_and_invoice_keep_it(): void
    {
        $id = $this->discount();

        $this->quoted(' save10 ')
            ->assertJsonPath('data.discount', ['code' => 'SAVE10', 'name' => 'Summer Sale', 'problem' => null])
            ->assertJsonPath('data.totals.subtotal', '19.00')
            ->assertJsonPath('data.totals.discount', '1.90')
            ->assertJsonPath('data.totals.grand_total', '22.10');

        $key = $this->place($this->freshCart(['ceramic-mug' => 2, 'tea-towel' => 1]), $this->order('22.10', ['discount_code' => 'save10']))->assertCreated()
            ->assertJsonPath('data.discount_code', 'SAVE10')
            ->assertJsonPath('data.totals.discount', '1.90')
            ->json('data.key');
        $order = EcommerceOrder::sole();
        $this->assertSame(['name' => 'Summer Sale', 'code' => 'SAVE10', 'kind' => 'percentage', 'value' => '10.0000', 'off' => '1.90'], $order->promotion);
        $this->assertSame([$id, '22.10', 'Discount code SAVE10 (Summer Sale)'], [$order->ecommerce_discount_id, (string) $order->grand_total, $order->discount_reason]);
        // Each line shows as it was before the discount.
        $this->assertSame(['16.00', '3.00', '5.00'], $order->lines->pluck('shown_amount')->map(fn ($amount) => (string) $amount)->all());

        Sanctum::actingAs($this->user);
        $this->getJson(self::ORDERS."/{$order->id}")->assertOk()
            ->assertJsonPath('data.discount', ['type' => 'amount', 'value' => '1.9000', 'reason' => 'Discount code SAVE10 (Summer Sale)'])
            ->assertJsonPath('data.totals.discount', '1.90');
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Online order EC-00001 placed on Rahman Shop by Karim Rahman, with code SAVE10');
        // Its confirmation e-mail names the code, as its page does.
        Mail::assertSent(StoreOrderMail::class, fn (StoreOrderMail $mail) => ($mail->order['totals']['Discount (SAVE10)'] ?? null) === '−'.$this->money('1.90'));
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk()->assertJsonPath('data.invoice.balance_due', '22.10');
        $this->getJson(self::DISCOUNTS)->assertJsonPath('data.0.uses', 1);
        $this->getJson(self::STORE."/orders/{$key}")->assertJsonPath('data.discount_code', 'SAVE10');
    }

    public function test_a_discount_the_business_changes_no_longer_names_the_code(): void
    {
        $this->discount();
        $key = $this->place($this->freshCart(['ceramic-mug' => 2, 'tea-towel' => 1]), $this->order('22.10', ['discount_code' => 'SAVE10']))->assertCreated()->json('data.key');
        $order = EcommerceOrder::sole();

        Sanctum::actingAs($this->user);
        $this->putJson(self::ORDERS."/{$order->id}/discount", ['type' => 'amount', 'value' => 5, 'reason' => 'A late delivery'])->assertOk();

        // The code stays on record - and its use counted - but the order's discount is the business's now.
        $this->assertSame('SAVE10', $order->fresh()->promotion['code']);
        $this->getJson(self::DISCOUNTS)->assertJsonPath('data.0.uses', 1);
        $this->getJson(self::STORE."/orders/{$key}")->assertJsonPath('data.discount_code', null)->assertJsonPath('data.totals.discount', '5.00');
    }

    public function test_a_code_is_refused_saying_why(): void
    {
        $this->quoted('NOPE')->assertJsonPath('data.discount.problem', 'This discount code is not valid.')->assertJsonPath('data.totals.discount', '0.00');
        $this->place($this->freshCart(['ceramic-mug' => 1]), $this->order('13.00', ['discount_code' => 'nope']))->assertUnprocessable()
            ->assertJsonPath('errors.discount_code.0', 'This discount code is not valid.');

        $id = $this->discount(['starts_on' => '2026-11-28']);
        $this->quoted('SAVE10')->assertJsonPath('data.discount.problem', 'This code works from 28 Nov 2026.');
        $this->putJson(self::DISCOUNTS."/{$id}", ['name' => 'Summer Sale', 'code' => 'SAVE10', 'kind' => 'percentage', 'value' => 10, 'applies_to' => 'order', 'ends_on' => '2026-11-26'])->assertOk();
        $this->quoted('SAVE10')->assertJsonPath('data.discount.problem', 'This code ended on 26 Nov 2026.');
        $this->putJson(self::DISCOUNTS."/{$id}", ['name' => 'Summer Sale', 'code' => 'SAVE10', 'kind' => 'percentage', 'value' => 10, 'applies_to' => 'order', 'minimum' => 50])->assertOk();
        $this->quoted('SAVE10')->assertJsonPath('data.discount.problem', 'This code is for orders of '.$this->money('50.00').' or more.');
        $this->putJson(self::DISCOUNTS."/{$id}", ['name' => 'Summer Sale', 'code' => 'SAVE10', 'kind' => 'percentage', 'value' => 10, 'applies_to' => 'order', 'is_active' => false])->assertOk();
        $this->quoted('SAVE10')->assertJsonPath('data.discount.problem', 'This discount code is not valid.');

        // For chosen products: refused without them, and off them alone with them.
        $towel = CatalogProduct::where('title', 'Tea Towel')->value('id');
        $this->discount(['name' => 'Towels', 'code' => 'TOWEL', 'kind' => 'amount', 'value' => 10, 'applies_to' => 'products', 'product_ids' => [$towel]]);
        $this->quoted('TOWEL', products: ['ceramic-mug' => 2])->assertJsonPath('data.discount.problem', 'This code is for products not in your cart.');
        $this->quoted('TOWEL')->assertJsonPath('data.totals.discount', '3.00')->assertJsonPath('data.totals.grand_total', '21.00');
    }

    public function test_a_code_is_used_as_often_as_it_may_be_and_a_cancelled_order_gives_its_use_back(): void
    {
        $this->discount(['usage_limit' => 1, 'per_customer_limit' => 1]);
        $this->place($this->freshCart(['ceramic-mug' => 1]), $this->order('12.20', ['discount_code' => 'SAVE10']))->assertCreated();

        $this->quoted('SAVE10', 'karim@example.test')->assertJsonPath('data.discount.problem', 'This code has been used as often as it may be.');

        // Cancelled, its order gives the use back - and one customer may use it once.
        Sanctum::actingAs($this->user);
        $this->postJson(self::ORDERS.'/'.EcommerceOrder::sole()->id.'/cancel', ['reason' => 'Asked to.'])->assertOk();
        $this->quoted('SAVE10', 'nadia@example.test')->assertJsonPath('data.discount.problem', null);
        $this->putJson(self::DISCOUNTS.'/'.EcommerceOrder::sole()->ecommerce_discount_id, ['name' => 'Summer Sale', 'code' => 'SAVE10', 'kind' => 'percentage', 'value' => 10, 'applies_to' => 'order', 'per_customer_limit' => 1])->assertOk();
        $this->place($this->freshCart(['ceramic-mug' => 1]), $this->order('12.20', ['discount_code' => 'SAVE10']))->assertCreated();
        $this->quoted('SAVE10', 'KARIM@example.test')->assertJsonPath('data.discount.problem', 'You have used this code as often as it may be.');
        $this->quoted('SAVE10', 'nadia@example.test')->assertJsonPath('data.discount.problem', null);
    }

    public function test_an_amount_never_takes_off_more_than_the_products_and_free_shipping_takes_the_shipping(): void
    {
        $this->discount(['name' => 'Five Off', 'code' => 'FIVE', 'kind' => 'amount', 'value' => 5]);
        $this->discount(['name' => 'Fifty Off', 'code' => 'FIFTY', 'kind' => 'amount', 'value' => 50]);
        $this->discount(['name' => 'Free Delivery', 'code' => 'SHIPFREE', 'kind' => 'free_shipping']);

        $this->quoted('FIVE')->assertJsonPath('data.totals.discount', '5.00')->assertJsonPath('data.totals.grand_total', '19.00');
        $this->quoted('FIFTY')->assertJsonPath('data.totals.discount', '19.00')->assertJsonPath('data.totals.grand_total', '5.00');
        $this->quoted('SHIPFREE')->assertJsonPath('data.totals.discount', '5.00')->assertJsonPath('data.totals.grand_total', '19.00');

        // Collected, there is no shipping to take off: the order keeps no code.
        $pickup = ['delivery' => 'pickup', 'shipping_method_id' => null, 'pickup_point_id' => $this->ids['Dhaka'], 'payment_method_id' => $this->ids['Pay at the Shop'], 'discount_code' => 'SHIPFREE'];
        $this->place($this->freshCart(['ceramic-mug' => 1]), $this->order('8.00', $pickup))->assertCreated()->assertJsonPath('data.totals.discount', '0.00');
        $this->assertNull(EcommerceOrder::sole()->promotion);
    }
}
