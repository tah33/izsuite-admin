<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\EcommerceCart;
use App\Models\User\PriceList;
use App\Models\User\Workspace;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase D3: a storefront's cart (sections 19.13 and 19.14) - kept for
 * its visitor by a token, without signing in; what may be put in it; read
 * again as the store sells now; priced with tax and, with full Sales, the
 * storefront's price list; and expired after Settings → eCommerce's days.
 */
class EcommerceCartTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const CART = self::STORE.'/cart';

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront([
            'Ceramic Mug'  => ['sale_price' => '8.00', 'sku' => 'MUG-1', 'in_kitchen' => true],
            'Black Tea'    => ['sale_price' => '3.50'],
            'Custom Shelf' => ['sale_price' => '90.00'],
            'T-Shirt S'    => ['sale_price' => '10.00', 'sku' => 'TS-S'],
            'T-Shirt M'    => ['sale_price' => '10.00', 'sku' => 'TS-M'],
            'T-Shirt L'    => ['sale_price' => '12.00', 'sku' => 'TS-L'],
        ]);

        $this->listed('Ceramic Mug');
        $this->listed('Black Tea');
        $this->listed('Custom Shelf', ['sales_method' => 'quotation']);
        $this->listed('T-Shirt S', ['title' => 'Classic T-Shirt', 'slug' => 'classic-t-shirt']);
        $this->putJson('/api/v1/ecommerce/catalog/'.$this->items['T-Shirt S']->id.'/variants', ['options' => [['name' => 'Size', 'values' => ['S', 'M', 'L']]], 'variants' => [
            ['item_id' => $this->items['T-Shirt S']->id, 'option_values' => ['S'], 'status' => 'active', 'availability' => 'out_of_stock'],
            ['item_id' => $this->items['T-Shirt M']->id, 'option_values' => ['M'], 'status' => 'hidden', 'availability' => 'available'],
            ['item_id' => $this->items['T-Shirt L']->id, 'option_values' => ['L'], 'status' => 'active', 'availability' => 'limited', 'stock_quantity' => 3],
        ]])->assertOk();
    }

    /** Adds to the cart of $token - a new cart without one - and gives back the answer. */
    private function add(?string $token, array $fields)
    {
        return $this->withHeaders($token ? ['X-Cart-Token' => $token] : [])->postJson(self::CART.'/lines', $fields);
    }

    private function cart(string $token)
    {
        return $this->withHeaders(['X-Cart-Token' => $token])->getJson(self::CART);
    }

    private function variant(string $item): int
    {
        return $this->items[$item]->catalogVariant->id;
    }

    public function test_a_visitor_fills_a_cart_its_token_keeps(): void
    {
        $this->getJson(self::CART)->assertOk()
            ->assertJsonPath('data.token', null)
            ->assertJsonPath('data.lines', [])
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.ready', false);

        $token = $this->add(null, ['product' => 'ceramic-mug', 'quantity' => 2])->assertCreated()
            ->assertJsonPath('message', 'Added to your cart.')
            ->assertJsonPath('data.lines.0.title', 'Ceramic Mug')
            ->assertJsonPath('data.lines.0.sku', 'MUG-1')
            ->assertJsonPath('data.lines.0.quantity', 2)
            ->assertJsonPath('data.lines.0.unit_price', '8.00')
            ->assertJsonPath('data.lines.0.amount', '16.00')
            ->assertJsonPath('data.subtotal', '16.00')
            ->assertJsonPath('data.grand_total', '16.00')
            ->assertJsonPath('data.ready', true)
            ->json('data.token');
        $this->assertSame(48, strlen($token));

        // The same product again joins its line; another makes one of its own. Its token is given only once.
        $this->add($token, ['product' => 'ceramic-mug', 'quantity' => 2])->assertOk()->assertJsonPath('data.token', null)->assertJsonPath('data.lines.0.quantity', 4);
        $line = $this->add($token, ['product' => 'black-tea', 'quantity' => 1])->assertOk()
            ->assertJsonPath('data.count', 5)
            ->assertJsonPath('data.subtotal', '35.50')
            ->json('data.lines.1.id');

        $this->withHeaders(['X-Cart-Token' => $token])->putJson(self::CART.'/lines/'.$line, ['quantity' => 3])->assertOk()
            ->assertJsonPath('message', 'Cart updated.')
            ->assertJsonPath('data.lines.1.amount', '10.50');
        $this->withHeaders(['X-Cart-Token' => $token])->deleteJson(self::CART.'/lines/'.$line)->assertOk()
            ->assertJsonPath('message', 'Removed from your cart.')
            ->assertJsonPath('data.count', 4);

        // Kept by its token's hash alone, with its totals as last shown.
        $cart = EcommerceCart::sole();
        $this->assertSame(hash('sha256', $token), $cart->token_hash);
        $this->assertSame(['active', 'USD', '32.00'], [$cart->status, $cart->currency, (string) $cart->subtotal]);
        $this->cart($token)->assertJsonPath('data.lines.0.quantity', 4);

        // Another token, or none, is another visitor's: an empty cart; a line not of this cart is not found.
        $this->cart(str_repeat('x', 48))->assertJsonPath('data.lines', []);
        $this->withHeaders(['X-Cart-Token' => str_repeat('x', 48)])->putJson(self::CART.'/lines/'.$cart->lines()->value('id'), ['quantity' => 1])
            ->assertNotFound()->assertJsonPath('message', 'Your cart has expired. Add the products again.');
    }

    public function test_only_what_the_store_sells_at_its_price_goes_in(): void
    {
        $this->add(null, ['product' => 'nothing-here', 'quantity' => 1])->assertJsonValidationErrors(['product' => 'This product is not sold in this store.']);
        $this->add(null, ['product' => 'custom-shelf', 'quantity' => 1])->assertJsonValidationErrors(['product' => 'This product is not sold online at a price: ask the store about it.']);
        $this->add(null, ['product' => 'ceramic-mug', 'quantity' => 0])->assertJsonValidationErrors(['quantity' => 'Add one at least.']);
        $this->add(null, ['product' => 'ceramic-mug', 'quantity' => 1000])->assertJsonValidationErrors(['quantity' => 'Up to 999 of a product.']);
        $this->add(null, ['product' => 'ceramic-mug', 'variant_id' => $this->variant('T-Shirt L'), 'quantity' => 1])->assertJsonValidationErrors(['variant_id' => 'This product has no options to choose.']);

        // A product with variants: one of those it shows, in stock, and no more than a limited one has left.
        $this->add(null, ['product' => 'classic-t-shirt', 'quantity' => 1])->assertJsonValidationErrors(['variant_id' => 'Choose its options.']);
        $this->add(null, ['product' => 'classic-t-shirt', 'variant_id' => $this->variant('T-Shirt M'), 'quantity' => 1])->assertJsonValidationErrors(['variant_id' => 'Choose its options.']);
        $this->add(null, ['product' => 'classic-t-shirt', 'variant_id' => $this->variant('T-Shirt S'), 'quantity' => 1])->assertJsonValidationErrors(['quantity' => 'Out of stock.']);
        $this->add(null, ['product' => 'classic-t-shirt', 'variant_id' => $this->variant('T-Shirt L'), 'quantity' => 4])->assertJsonValidationErrors(['quantity' => 'Only 3 left.']);
        $this->assertSame(0, EcommerceCart::count());

        $token = $this->add(null, ['product' => 'classic-t-shirt', 'variant_id' => $this->variant('T-Shirt L'), 'quantity' => 2])->assertCreated()
            ->assertJsonPath('data.lines.0.title', 'Classic T-Shirt')
            ->assertJsonPath('data.lines.0.options', [['name' => 'Size', 'value' => 'L']])
            ->assertJsonPath('data.lines.0.sku', 'TS-L')
            ->assertJsonPath('data.lines.0.unit_price', '12.00')
            ->json('data.token');
        $this->add($token, ['product' => 'classic-t-shirt', 'variant_id' => $this->variant('T-Shirt L'), 'quantity' => 2])->assertJsonValidationErrors(['quantity' => 'Only 3 left.']);

        // An item whose price is taken off is not added.
        $this->items['Black Tea']->forceFill(['sale_price' => null])->save();
        $this->add($token, ['product' => 'black-tea', 'quantity' => 1])->assertJsonValidationErrors(['product' => 'This product has no price yet: ask the store about it.']);
    }

    public function test_a_cart_is_read_as_the_store_sells_now(): void
    {
        $token = $this->add(null, ['product' => 'ceramic-mug', 'quantity' => 2])->json('data.token');
        $shirt = $this->add($token, ['product' => 'classic-t-shirt', 'variant_id' => $this->variant('T-Shirt L'), 'quantity' => 2])->json('data.lines.1.id');

        // A new price shows, with the one it replaced - once.
        $this->items['Ceramic Mug']->forceFill(['sale_price' => '9.00'])->save();
        $this->cart($token)->assertJsonPath('data.lines.0.unit_price', '9.00')->assertJsonPath('data.lines.0.price_was', '8.00')->assertJsonPath('data.subtotal', '42.00');
        $this->cart($token)->assertJsonPath('data.lines.0.price_was', null);

        // Fewer left than the cart holds: that line cannot be bought, nor the cart checked out, until it is changed.
        $this->items['T-Shirt L']->catalogVariant->forceFill(['stock_quantity' => 1])->save();
        $this->cart($token)
            ->assertJsonPath('data.lines.1.problem', 'limited')
            ->assertJsonPath('data.lines.1.left', 1)
            ->assertJsonPath('data.lines.1.amount', null)
            ->assertJsonPath('data.subtotal', '18.00')
            ->assertJsonPath('data.ready', false);
        $this->withHeaders(['X-Cart-Token' => $token])->putJson(self::CART.'/lines/'.$shirt, ['quantity' => 2])->assertJsonValidationErrors(['quantity' => 'Only 1 left.']);
        $this->withHeaders(['X-Cart-Token' => $token])->putJson(self::CART.'/lines/'.$shirt, ['quantity' => 1])->assertOk()->assertJsonPath('data.ready', true);

        // Taken off the store, it stays by name, unavailable.
        $this->items['Ceramic Mug']->catalogProduct->forceFill(['status' => 'draft'])->save();
        $this->cart($token)
            ->assertJsonPath('data.lines.0.title', 'Ceramic Mug')
            ->assertJsonPath('data.lines.0.problem', 'unavailable')
            ->assertJsonPath('data.lines.0.unit_price', null)
            ->assertJsonPath('data.ready', false);
        $this->withHeaders(['X-Cart-Token' => $token])->putJson(self::CART.'/lines/'.$this->cart($token)->json('data.lines.0.id'), ['quantity' => 1])
            ->assertJsonValidationErrors(['quantity' => 'This product is no longer sold here: remove it from your cart.']);

        // A store not open has no cart to show.
        $this->store->update(['status' => 'maintenance']);
        $this->cart($token)->assertNotFound()->assertJsonPath('message', 'This store is not open.');
    }

    public function test_prices_take_their_tax_and_the_storefronts_price_list(): void
    {
        $vat = $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT10', 'rate' => '10', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        app(TaxRateRepository::class)->setDefault($this->business, 'sales', $vat);
        $this->business->forceFill(['tax_mode' => Workspace::TAXES_ENABLED, 'price_tax_mode' => 'exclusive'])->save();

        // Shown with tax: prices kept without it gain it; the total is the same either way.
        $token = $this->add(null, ['product' => 'ceramic-mug', 'quantity' => 2])
            ->assertJsonPath('data.prices_include_tax', true)
            ->assertJsonPath('data.lines.0.unit_price', '8.80')
            ->assertJsonPath('data.lines.0.amount', '17.60')
            ->assertJsonPath('data.subtotal', '17.60')
            ->assertJsonPath('data.tax_total', '1.60')
            ->assertJsonPath('data.grand_total', '17.60')
            ->json('data.token');
        $this->putJson('/api/v1/business-settings/ecommerce', ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'without_tax', 'order_prefix' => 'EC-'])->assertOk();
        $this->cart($token)->assertJsonPath('data.lines.0.unit_price', '8.00')->assertJsonPath('data.subtotal', '16.00')->assertJsonPath('data.tax_total', '1.60')->assertJsonPath('data.grand_total', '17.60');

        // With full Sales, the storefront sells at its own price list - its quantity breaks too - on its pages and in the cart.
        $sales = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::first()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $sales->packages()->attach(Package::pluck('id'));
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
        $list  = PriceList::query()->forceCreate(['workspace_id' => $this->business->id, 'name' => 'Online Prices', 'currency' => 'USD', 'is_active' => true, 'all_customers' => true, 'all_locations' => true]);
        $euros = PriceList::query()->forceCreate(['workspace_id' => $this->business->id, 'name' => 'Euro Prices', 'currency' => 'EUR', 'is_active' => true, 'all_customers' => true, 'all_locations' => true]);
        $list->rules()->create(['target' => 'item', 'item_id' => $this->items['Ceramic Mug']->id, 'min_quantity' => 1, 'method' => 'fixed', 'value' => '7.00', 'position' => 1]);
        $list->rules()->create(['target' => 'item', 'item_id' => $this->items['Ceramic Mug']->id, 'min_quantity' => 3, 'method' => 'fixed', 'value' => '6.50', 'position' => 2]);

        $this->assertContains('Online Prices', collect($this->getJson('/api/v1/ecommerce/storefronts/options')->json('data.price_lists'))->pluck('name')->all());
        $storefront = ['name' => 'Rahman Shop', 'code' => 'rahman-shop', 'status' => 'live', 'default_language' => 'en', 'currency' => 'USD', 'countries' => ['BD'], 'location_id' => $this->store->location_id];
        $this->putJson('/api/v1/ecommerce/storefronts/'.$this->store->id, [...$storefront, 'price_list_id' => $euros->id])
            ->assertJsonValidationErrors(['price_list_id' => "Choose an active price list in the storefront's currency, for its location."]);
        $this->putJson('/api/v1/ecommerce/storefronts/'.$this->store->id, [...$storefront, 'price_list_id' => $list->id])->assertOk()->assertJsonPath('data.price_list.name', 'Online Prices');

        $this->getJson(self::STORE.'/products/ceramic-mug')->assertJsonPath('data.price', '7.00');
        $line = $this->cart($token)->assertJsonPath('data.lines.0.unit_price', '7.00')->assertJsonPath('data.grand_total', '15.40')->json('data.lines.0.id');
        $this->withHeaders(['X-Cart-Token' => $token])->putJson(self::CART.'/lines/'.$line, ['quantity' => 3])->assertOk()
            ->assertJsonPath('data.lines.0.unit_price', '6.50')
            ->assertJsonPath('data.subtotal', '19.50')
            ->assertJsonPath('data.grand_total', '21.45');
        $this->cart($token)->assertJsonPath('data.lines.0.price_was', null);

        // A form from before this phase, without the list, keeps it.
        $this->putJson('/api/v1/ecommerce/storefronts/'.$this->store->id, $storefront)->assertOk()->assertJsonPath('data.price_list.name', 'Online Prices');
    }

    public function test_a_cart_unused_past_the_settings_days_expires(): void
    {
        $this->getJson('/api/v1/business-settings/ecommerce')->assertJsonPath('data.cart_days', 30);
        $this->putJson('/api/v1/business-settings/ecommerce', ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'cart_days' => 0])
            ->assertJsonValidationErrors(['cart_days' => 'Keep carts for a day at least.']);
        $this->putJson('/api/v1/business-settings/ecommerce', ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'cart_days' => 2])
            ->assertOk()->assertJsonPath('data.cart_days', 2);

        $token = $this->add(null, ['product' => 'ceramic-mug', 'quantity' => 1])->json('data.token');

        // Used again within its days, it is kept two days more.
        $this->travel(36)->hours();
        $this->cart($token)->assertJsonPath('data.count', 1);
        $this->travel(36)->hours();
        $this->cart($token)->assertJsonPath('data.count', 1);

        $this->travel(3)->days();
        $this->cart($token)->assertJsonPath('data.lines', []);
        $this->artisan('ecommerce:expire-carts')->expectsOutput('Expired 1 cart.')->assertSuccessful();
        $this->assertSame(EcommerceCart::EXPIRED, EcommerceCart::sole()->status);

        // Adding with its token starts a new cart.
        $this->add($token, ['product' => 'ceramic-mug', 'quantity' => 1])->assertCreated();
    }
}
