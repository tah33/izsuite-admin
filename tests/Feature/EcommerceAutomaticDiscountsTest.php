<?php

namespace Tests\Feature;

use App\Mail\StoreOrderMail;
use App\Models\User\EcommerceOrder;
use App\Models\User\ShippingZone;
use App\Models\User\Workspace;
use App\Repositories\User\TaxRateRepository;
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
 * Automatic discounts, and how discounts go together at checkout (Doc 19
 * section 19.31 - Phase H2a): taken off every order they fit without a code,
 * by a code's rules; taken with the code given in order of priority - at the
 * same priority the one taking off more first - one that goes with none
 * alone; together never more than the products, nor the shipping twice; the
 * order keeping each, its discount theirs, as the store showed it.
 */
class EcommerceAutomaticDiscountsTest extends TestCase
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

    /** An automatic 10% off the whole order, Weekend Sale - or $fields over it, a code among them - its id. */
    private function discount(array $fields = []): int
    {
        Sanctum::actingAs($this->user);

        return $this->postJson(self::DISCOUNTS, ['name' => 'Weekend Sale', 'automatic' => ! isset($fields['code']), 'kind' => 'percentage', 'value' => 10, 'applies_to' => 'order', ...$fields])->assertCreated()->json('data.id');
    }

    private function change(int $id, array $fields)
    {
        Sanctum::actingAs($this->user);

        return $this->putJson(self::DISCOUNTS."/{$id}", ['applies_to' => 'order', ...$fields])->assertOk();
    }

    /** Two mugs ($16) and a towel ($3), shipped by Standard ($5), quoted with $code - for $email. */
    private function quoted(?string $code = null, ?string $email = 'karim@example.test')
    {
        $this->flushHeaders();

        return $this->quote($this->cartWith(['ceramic-mug' => 2, 'tea-towel' => 1]), [
            'address' => ['country' => 'BD'], 'billing_country' => 'BD', 'delivery' => 'shipping', 'shipping_method_id' => $this->ids['Standard'],
            'payment_method_id' => $this->ids['Bank Transfer'], 'discount_code' => $code, 'email' => $email,
        ])->assertOk();
    }

    private function placed(string $total, array $fields = [])
    {
        $this->flushHeaders();

        return $this->place($this->cartWith(['ceramic-mug' => 2, 'tea-towel' => 1]), $this->order($total, $fields));
    }

    private function money(string $amount): string
    {
        return (new DocumentFormat('d M Y', 'comma_dot'))->money($amount, 'USD');
    }

    public function test_an_automatic_discount_is_taken_off_every_order_it_fits_and_kept_with_it(): void
    {
        $id = $this->discount();

        $this->quoted()
            ->assertJsonPath('data.discount', null)
            ->assertJsonPath('data.discounts', [['name' => 'Weekend Sale', 'code' => null, 'off' => '1.90']])
            ->assertJsonPath('data.totals.discount', '1.90')
            ->assertJsonPath('data.totals.grand_total', '22.10');

        $key = $this->placed('22.10')->assertCreated()->assertJsonPath('data.discounts', [['name' => 'Weekend Sale', 'code' => null, 'off' => '1.90']])->json('data.key');
        $order = EcommerceOrder::sole();
        $this->assertSame([[$id, 'Weekend Sale', null, '1.90']], $order->promotions->map(fn ($promotion) => [$promotion->ecommerce_discount_id, $promotion->name, $promotion->code, (string) $promotion->off])->all());
        $this->assertSame(['amount', '1.9000', 'Weekend Sale'], [$order->discount_type, (string) $order->discount_value, $order->discount_reason]);
        Mail::assertSent(StoreOrderMail::class, fn (StoreOrderMail $mail) => ($mail->order['totals']['Weekend Sale'] ?? null) === '−'.$this->money('1.90'));

        Sanctum::actingAs($this->user);
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Online order EC-00001 placed on Rahman Shop by Karim Rahman, with Weekend Sale');
        $this->getJson(self::DISCOUNTS)->assertJsonPath('data.0.uses', 1);
        $this->getJson(self::STORE."/orders/{$key}")->assertJsonPath('data.discounts.0.name', 'Weekend Sale');
    }

    public function test_an_automatic_discount_that_does_not_fit_is_simply_not_taken(): void
    {
        // Its minimum, its days, its uses by one customer, and inactive: none says why - each is simply left out.
        $first = $this->discount(['name' => 'First Order', 'kind' => 'amount', 'value' => 5, 'per_customer_limit' => 1]);
        $this->discount(['name' => 'Big Spender', 'minimum' => 50]);
        $this->discount(['name' => 'Next Week', 'starts_on' => '2026-12-01']);
        $this->discount(['name' => 'Paused', 'is_active' => false]);

        $this->quoted()->assertJsonPath('data.discounts', [['name' => 'First Order', 'code' => null, 'off' => '5.00']]);
        $this->placed('19.00')->assertCreated();

        // Karim has had it once; Nadia not yet - and without an e-mail it is not yet known.
        $this->quoted(email: 'Karim@Example.test')->assertJsonPath('data.discounts', [])->assertJsonPath('data.totals.grand_total', '24.00');
        $this->quoted(email: 'nadia@example.test')->assertJsonPath('data.discounts.0.name', 'First Order');
        $this->quoted(email: null)->assertJsonPath('data.discounts.0.name', 'First Order');

        // Cancelled, the order gives its use back.
        Sanctum::actingAs($this->user);
        $this->postJson(self::ORDERS.'/'.EcommerceOrder::sole()->id.'/cancel', ['reason' => 'Asked to.'])->assertOk();
        $this->quoted()->assertJsonPath('data.discounts.0.name', 'First Order');
        $this->getJson(self::DISCOUNTS.'?search=first')->assertJsonPath('data.0.id', $first)->assertJsonPath('data.0.uses', 0);
    }

    public function test_discounts_are_taken_by_priority_one_that_goes_with_none_alone(): void
    {
        $weekend = $this->discount();                                                                   // $1.90
        $save    = $this->discount(['name' => 'Five Off', 'code' => 'SAVE5', 'kind' => 'amount', 'value' => 5]);

        // At the same priority the one taking off more is taken: the code's $5, the automatic one left out.
        $this->quoted('SAVE5')
            ->assertJsonPath('data.discount', ['code' => 'SAVE5', 'name' => 'Five Off', 'problem' => null])
            ->assertJsonPath('data.discounts', [['name' => 'Five Off', 'code' => 'SAVE5', 'off' => '5.00']]);

        // Taking off less, the code is refused saying why - and placing with it is refused under it.
        $this->change($save, ['name' => 'One Off', 'code' => 'SAVE5', 'kind' => 'amount', 'value' => 1]);
        $this->quoted('SAVE5')
            ->assertJsonPath('data.discount.problem', 'This code does not go with Weekend Sale, already on your order.')
            ->assertJsonPath('data.discounts', [['name' => 'Weekend Sale', 'code' => null, 'off' => '1.90']]);
        $this->placed('22.10', ['discount_code' => 'SAVE5'])->assertUnprocessable()->assertJsonPath('errors.discount_code.0', 'This code does not go with Weekend Sale, already on your order.');

        // Priority before what they take off: the code first at 1, the automatic one at 2 left out.
        $this->change($weekend, ['name' => 'Weekend Sale', 'automatic' => true, 'kind' => 'percentage', 'value' => 10, 'priority' => 2]);
        $this->quoted('SAVE5')->assertJsonPath('data.discount.problem', null)->assertJsonPath('data.discounts.*.name', ['One Off']);

        // Both going with others: both, in order of priority - $2.90 off.
        $this->change($weekend, ['name' => 'Weekend Sale', 'automatic' => true, 'kind' => 'percentage', 'value' => 10, 'priority' => 2, 'combines' => true]);
        $this->change($save, ['name' => 'One Off', 'code' => 'SAVE5', 'kind' => 'amount', 'value' => 1, 'combines' => true]);
        $this->quoted('SAVE5')
            ->assertJsonPath('data.discounts', [['name' => 'One Off', 'code' => 'SAVE5', 'off' => '1.00'], ['name' => 'Weekend Sale', 'code' => null, 'off' => '1.90']])
            ->assertJsonPath('data.totals.discount', '2.90')
            ->assertJsonPath('data.totals.grand_total', '21.10');

        // One that goes with none, taken first, keeps the rest out - and a code that goes with others is still refused by it.
        $this->discount(['name' => 'Clearance', 'kind' => 'amount', 'value' => 4]);
        $this->quoted('SAVE5')
            ->assertJsonPath('data.discount.problem', 'This code does not go with Clearance, already on your order.')
            ->assertJsonPath('data.discounts.*.name', ['Clearance']);

        $order = $this->placed('20.00')->assertCreated()->json('data.discounts');
        $this->assertSame([['name' => 'Clearance', 'code' => null, 'off' => '4.00']], $order);
    }

    public function test_together_they_never_take_off_more_than_the_products_nor_the_shipping_twice(): void
    {
        $this->discount(['name' => 'Half Off', 'value' => 50, 'combines' => true]);                                        // $9.50
        $this->discount(['name' => 'Big Off', 'code' => 'BIG', 'kind' => 'amount', 'value' => 15, 'combines' => true]);  // $15, then what is left

        $this->quoted('BIG')
            ->assertJsonPath('data.discounts', [['name' => 'Big Off', 'code' => 'BIG', 'off' => '15.00'], ['name' => 'Half Off', 'code' => null, 'off' => '4.00']])
            ->assertJsonPath('data.totals.discount', '19.00')
            ->assertJsonPath('data.totals.grand_total', '5.00');

        // Free shipping twice takes the shipping once; the second, with nothing left to take off, is left out.
        Sanctum::actingAs($this->user);
        $this->deleteJson(self::DISCOUNTS.'/'.$this->getJson(self::DISCOUNTS.'?search=half')->json('data.0.id'))->assertOk();
        $this->deleteJson(self::DISCOUNTS.'/'.$this->getJson(self::DISCOUNTS.'?search=big')->json('data.0.id'))->assertOk();
        $this->discount(['name' => 'Free Delivery', 'kind' => 'free_shipping', 'combines' => true]);
        $this->discount(['name' => 'Ship Free', 'code' => 'SHIP', 'kind' => 'free_shipping', 'combines' => true, 'priority' => 2]);

        $this->quoted('SHIP')
            ->assertJsonPath('data.discount.problem', null)
            ->assertJsonPath('data.discounts', [['name' => 'Free Delivery', 'code' => null, 'off' => '5.00']])
            ->assertJsonPath('data.totals.grand_total', '19.00');
    }

    public function test_with_prices_shown_with_tax_the_order_keeps_its_discounts_as_the_store_showed_them(): void
    {
        $vat = $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT10', 'rate' => '10', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        app(TaxRateRepository::class)->setDefault($this->business, 'sales', $vat);
        $this->business->forceFill(['tax_mode' => Workspace::TAXES_ENABLED, 'price_tax_mode' => 'exclusive'])->save();
        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/business-settings/ecommerce/shipping-methods/'.$this->ids['Standard'], ['shipping_zone_id' => ShippingZone::sole()->id, 'name' => 'Standard', 'kind' => 'flat', 'price' => 5, 'tax_rate_id' => $vat->id])->assertOk();

        $this->discount(['combines' => true]);
        $this->discount(['name' => 'Members', 'code' => 'MEMBER', 'combines' => true]);

        // 10% of the products - $1.90 before tax - is $2.09 as the store shows prices, each: $26.40 less $4.18.
        $this->quoted('MEMBER')
            ->assertJsonPath('data.discounts.*.off', ['2.09', '2.09'])
            ->assertJsonPath('data.totals.discount', '4.18')
            ->assertJsonPath('data.totals.grand_total', '22.22');

        $key = $this->placed('22.22', ['discount_code' => 'MEMBER'])->assertCreated()
            ->assertJsonPath('data.totals.discount', '4.18')
            ->assertJsonPath('data.discounts.*.off', ['2.09', '2.09'])
            ->json('data.key');
        $order = EcommerceOrder::sole();
        $this->assertSame(['3.8000', '4.18', '22.22'], [(string) $order->discount_value, (string) $order->discount_total, (string) $order->grand_total]);
        $this->getJson(self::STORE."/orders/{$key}")->assertJsonPath('data.totals.discount', '4.18');
    }
}
