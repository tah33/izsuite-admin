<?php

namespace Tests\Feature;

use App\Models\User\CatalogProduct;
use App\Models\User\Storefront;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\TaxRateRepository;
use App\Support\BusinessStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase C1: the storefront opens (sections 19.5, 19.6, 19.12 and
 * 19.13) - a store by its code, without signing in: whether it is open, the
 * products it shows, searched, narrowed and sorted, a product's page with its
 * variants, and its prices with or without tax.
 */
class EcommerceStorefrontShopTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront([
            'Ceramic Mug' => ['sale_price' => '8.00', 'sku' => 'MUG-1', 'in_kitchen' => true],
            'Black Tea'   => ['sale_price' => '3.50', 'sku' => 'TEA-B', 'by_rahman' => true],
            'Green Tea'   => ['sale_price' => '4.00', 'sku' => 'TEA-G', 'by_rahman' => true],
            'T-Shirt S'   => ['sale_price' => '10.00', 'sku' => 'TS-S'],
            'T-Shirt M'   => ['sale_price' => '10.00', 'sku' => 'TS-M'],
            'T-Shirt L'   => ['sale_price' => '12.00', 'sku' => 'TS-L'],
        ]);
    }

    public function test_a_store_says_whether_it_is_open(): void
    {
        $this->listed('Ceramic Mug', ['short_description' => 'A sturdy mug.']);
        Sanctum::actingAs(User::factory()->create()); // Anyone at all: signed in or not, it answers the same.

        $this->getJson('/api/v1/stores/nobody')->assertNotFound()->assertJsonPath('message', 'There is no such store.');
        $this->getJson(self::STORE)->assertOk()
            ->assertJsonPath('data.open', true)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.prices_include_tax', null)
            ->assertJsonPath('data.support.email', 'help@rahman.test')
            ->assertJsonPath('data.menu', [['name' => 'Kitchen', 'slug' => 'kitchen']])
            ->assertJsonPath('data.brands', []);

        foreach ([['status' => Storefront::MAINTENANCE], ['status' => Storefront::DRAFT]] as $fields) {
            $this->store->update($fields);
            $this->getJson(self::STORE)->assertOk()->assertExactJson(['data' => ['name' => 'Rahman Shop', 'code' => 'rahman-shop', 'open' => false, 'reason' => $fields['status'] === Storefront::MAINTENANCE ? 'maintenance' : 'closed']]);
            $this->getJson(self::STORE.'/products')->assertNotFound()->assertJsonPath('message', 'This store is not open.');
            $this->getJson(self::STORE.'/products/ceramic-mug')->assertNotFound();
        }

        // Live again: closed all the same while its business is suspended, or has eCommerce off.
        $this->store->update(['status' => Storefront::LIVE]);
        $this->business->forceFill(['status' => BusinessStatus::SUSPENDED])->save();
        $this->getJson(self::STORE)->assertJsonPath('data.open', false);
        $this->business->forceFill(['status' => BusinessStatus::ACTIVE])->save();
        $this->business->workspaceApps()->where('family_key', 'ecommerce')->update(['status' => 'inactive']);
        $this->getJson(self::STORE)->assertJsonPath('data.open', false);
    }

    public function test_a_store_shows_what_is_published_for_everyone_on_it(): void
    {
        $other = $this->business->storefronts()->create(['name' => 'Outlet', 'code' => 'rahman-outlet', 'status' => 'live', 'default_language' => 'en', 'languages' => [], 'currency' => 'USD', 'currencies' => [], 'countries' => [], 'location_id' => $this->store->location_id]);

        $this->listed('Ceramic Mug');
        $this->listed('Black Tea', ['status' => 'scheduled', 'available_from' => now()->subDay()->toDateString()]);
        $this->listed('Green Tea', ['status' => 'scheduled', 'available_from' => now()->addDays(2)->toDateString()]);
        $this->listed('T-Shirt S', ['available_until' => now()->subDay()->toDateString()]);
        $this->listed('T-Shirt M', ['visibility' => 'link']);
        $this->listed('T-Shirt L', ['all_storefronts' => false], [$other->id]);

        $this->assertSame(['Black Tea', 'Ceramic Mug'], $this->shown('?sort=title'));

        // Its link opens one kept for its link; signed-in customers' wait for their accounts.
        $this->getJson(self::STORE.'/products/t-shirt-m')->assertOk()->assertJsonPath('data.title', 'T-Shirt M');
        CatalogProduct::where('slug', 't-shirt-m')->update(['visibility' => 'customers']);
        $this->getJson(self::STORE.'/products/t-shirt-m')->assertNotFound()->assertJsonPath('message', 'There is no such product in this store.');
        $this->getJson('/api/v1/stores/rahman-outlet/products/t-shirt-l')->assertOk();
        $this->getJson(self::STORE.'/products/t-shirt-l')->assertNotFound();

        // An item switched off, or a listing drafted, hidden or archived, is not shown.
        $this->items['Ceramic Mug']->update(['is_active' => false]);
        CatalogProduct::where('slug', 'black-tea')->update(['status' => 'hidden']);
        $this->assertSame([], $this->shown());
    }

    public function test_products_are_searched_narrowed_and_sorted(): void
    {
        $this->listed('Ceramic Mug', ['keywords' => 'cup, coffee']);
        $this->listed('Black Tea', ['short_description' => 'Strong and malty.']);
        $this->listed('Green Tea', ['availability' => 'out_of_stock']);
        $brand = $this->items['Black Tea']->brand_id;

        $this->assertSame(['Ceramic Mug'], $this->shown('?search=coffee'));
        $this->assertSame(['Ceramic Mug'], $this->shown('?search=MUG-1'));
        $this->assertSame(['Ceramic Mug'], $this->shown('?search=kitchen'));
        $this->assertSame(['Black Tea'], $this->shown('?search=malty'));
        $this->assertSame(['Green Tea', 'Black Tea'], $this->shown('?search=rahman'));
        $this->assertSame(['Ceramic Mug'], $this->shown('?category=kitchen'));
        $this->assertSame(['Black Tea', 'Green Tea'], $this->shown("?brand={$brand}&sort=title"));
        $this->assertSame(['Green Tea', 'Black Tea', 'Ceramic Mug'], $this->shown());

        // By price: one without a price last, either way.
        $this->items['Shelf'] = $this->business->items()->create(['type' => 'physical', 'unit' => 'Piece', 'usage' => 'sales', 'name' => 'Shelf']);
        $this->listed('Shelf', ['sales_method' => 'quotation']);
        $this->assertSame(['Black Tea', 'Green Tea', 'Ceramic Mug', 'Shelf'], $this->shown('?sort=price_low'));
        $this->assertSame(['Ceramic Mug', 'Green Tea', 'Black Tea', 'Shelf'], $this->shown('?sort=price_high'));
        $this->assertSame(['Black Tea', 'Ceramic Mug', 'Shelf'], $this->shown('?in_stock=1&sort=title'));
        $this->getJson(self::STORE)->assertJsonPath('data.brands', [['id' => $brand, 'name' => 'Rahman']]);
        $this->getJson(self::STORE.'/products?sort=cheapest')->assertJsonValidationErrors('sort');
    }

    public function test_prices_show_tax_as_the_settings_say_with_the_variants_shown(): void
    {
        $vat = $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT10', 'rate' => '10', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        app(TaxRateRepository::class)->setDefault($this->business, 'sales', $vat);
        $this->business->forceFill(['tax_mode' => Workspace::TAXES_ENABLED, 'price_tax_mode' => 'exclusive'])->save();

        $shirt = $this->listed('T-Shirt S', ['title' => 'Classic T-Shirt', 'slug' => 'classic-t-shirt', 'custom_attributes' => [['name' => 'Material', 'value' => 'Cotton']]]);
        $this->putJson('/api/v1/ecommerce/catalog/'.$this->items['T-Shirt S']->id.'/variants', ['options' => [['name' => 'Size', 'values' => ['S', 'M', 'L']]], 'variants' => [
            ['item_id' => $this->items['T-Shirt S']->id, 'option_values' => ['S'], 'status' => 'active', 'availability' => 'out_of_stock'],
            ['item_id' => $this->items['T-Shirt M']->id, 'option_values' => ['M'], 'status' => 'hidden', 'availability' => 'available'],
            ['item_id' => $this->items['T-Shirt L']->id, 'option_values' => ['L'], 'status' => 'active', 'availability' => 'limited', 'stock_quantity' => 3],
        ]])->assertOk();

        // With tax (the default), prices taken without it gain it; its variants' differ, the hidden one aside.
        $this->getJson(self::STORE)->assertJsonPath('data.prices_include_tax', true);
        $this->getJson(self::STORE.'/products')->assertJsonPath('data.0.price', '11.00')->assertJsonPath('data.0.price_varies', true)->assertJsonPath('data.0.in_stock', true);
        $this->getJson(self::STORE.'/products/classic-t-shirt')->assertOk()
            ->assertJsonPath('data.price', '11.00')
            ->assertJsonPath('data.options', [['name' => 'Size', 'values' => ['S', 'M', 'L']]])
            ->assertJsonPath('data.variants.*.option_values', [['S'], ['L']])
            ->assertJsonPath('data.variants.*.price', ['11.00', '13.20'])
            ->assertJsonPath('data.variants.1.stock_quantity', '3.000')
            ->assertJsonPath('data.attributes', [['name' => 'Material', 'value' => 'Cotton']]);

        // Without tax: as taken. Prices taken with tax lose it.
        $this->putJson('/api/v1/business-settings/ecommerce', ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'without_tax', 'order_prefix' => 'EC-'])->assertOk();
        $this->getJson(self::STORE.'/products/classic-t-shirt')->assertJsonPath('data.price', '10.00');
        $this->business->forceFill(['price_tax_mode' => 'inclusive'])->save();
        $this->getJson(self::STORE.'/products/classic-t-shirt')->assertJsonPath('data.variants.*.price', ['9.09', '10.91']);
        $this->getJson(self::STORE)->assertJsonPath('data.prices_include_tax', false);

        // Out of stock throughout: the list says so, and the in-stock filter leaves it out.
        $shirt->variants()->update(['availability' => 'out_of_stock']);
        $this->getJson(self::STORE.'/products')->assertJsonPath('data.0.in_stock', false);
        $this->assertSame([], $this->shown('?in_stock=1'));
    }
}
