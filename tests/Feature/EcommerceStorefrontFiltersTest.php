<?php

namespace Tests\Feature;

use App\Models\User\Workspace;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase C3: a storefront's filters by price, options and attributes
 * (section 19.11) - each offering what the products shown have, and the
 * products narrowed by them, a price as the store shows it.
 */
class EcommerceStorefrontFiltersTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront([
            'T-Shirt S'   => ['sale_price' => '10.00'],
            'T-Shirt M'   => ['sale_price' => '10.00'],
            'T-Shirt L'   => ['sale_price' => '12.00'],
            'Ceramic Mug' => ['sale_price' => '8.00', 'in_kitchen' => true],
            'Cup'         => ['sale_price' => '4.00'],
        ]);

        $this->listed('T-Shirt S', ['title' => 'Classic T-Shirt', 'slug' => 'classic-t-shirt', 'custom_attributes' => [['name' => 'Material', 'value' => 'Cotton']]]);
        $this->listed('Ceramic Mug', ['custom_attributes' => [['name' => 'Material', 'value' => 'Stoneware'], ['name' => 'Capacity', 'value' => '350 ml']]]);
        $this->listed('Cup', ['custom_attributes' => [['name' => 'material', 'value' => 'Paper']]]);

        $variant = fn (string $name, array $values) => ['item_id' => $this->items[$name]->id, 'option_values' => $values, 'status' => 'active', 'availability' => 'available'];
        $this->putJson('/api/v1/ecommerce/catalog/'.$this->items['T-Shirt S']->id.'/variants', [
            'options'  => [['name' => 'Size', 'values' => ['S', 'M', 'L']], ['name' => 'Colour', 'values' => ['Red', 'Blue']]],
            'variants' => [$variant('T-Shirt S', ['S', 'Red']), $variant('T-Shirt M', ['M', 'Blue']), $variant('T-Shirt L', ['L', 'Red'])],
        ])->assertOk();
    }

    public function test_the_filters_offer_what_the_products_shown_have(): void
    {
        $this->getJson(self::STORE.'/facets')->assertOk()->assertExactJson(['data' => [
            'price'      => ['min' => '4.00', 'max' => '12.00'],
            'options'    => [['name' => 'Colour', 'values' => ['Red', 'Blue']], ['name' => 'Size', 'values' => ['S', 'M', 'L']]],
            'attributes' => [['name' => 'Capacity', 'values' => ['350 ml']], ['name' => 'Material', 'values' => ['Cotton', 'Stoneware', 'Paper']]],
        ]]);

        // Within a category, only what its products have.
        $this->getJson(self::STORE.'/facets?category=kitchen')->assertOk()->assertExactJson(['data' => [
            'price'      => ['min' => '8.00', 'max' => '8.00'],
            'options'    => [],
            'attributes' => [['name' => 'Capacity', 'values' => ['350 ml']], ['name' => 'Material', 'values' => ['Stoneware']]],
        ]]);
        $this->getJson(self::STORE.'/facets?search=nothing-like-it')->assertOk()->assertJsonPath('data.price', null);
    }

    public function test_products_are_filtered_by_price_options_and_attributes(): void
    {
        $this->assertSame(['Classic T-Shirt'], $this->shown('?price_min=9'));
        $this->assertSame(['Ceramic Mug', 'Cup'], $this->shown('?price_max=8&sort=title'));
        $this->assertSame([], $this->shown('?price_min=11&price_max=11.5'));

        // One variant has every value asked for, whatever their case.
        $this->assertSame(['Classic T-Shirt'], $this->shown('?option[Size]=M'));
        $this->assertSame([], $this->shown('?option[Size]=M&option[Colour]=Red'));
        $this->assertSame(['Classic T-Shirt'], $this->shown('?option[size]=L&option[Colour]=red'));

        $this->assertSame(['Ceramic Mug'], $this->shown('?attribute[Material]=stoneware'));
        $this->assertSame(['Cup'], $this->shown('?attribute[Material]=Paper'));
        $this->assertSame(['Classic T-Shirt'], $this->shown('?attribute[Material]=Cotton&option[Size]=S'));
        $this->assertSame([], $this->shown('?attribute[Colour]=Red'));
        $this->assertSame(['Ceramic Mug'], $this->shown('?category=kitchen&attribute[Material]=Stoneware'));
        $this->getJson(self::STORE.'/products?price_min=cheap')->assertJsonValidationErrors('price_min');
    }

    public function test_a_price_is_filtered_as_the_store_shows_it(): void
    {
        $vat = $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT10', 'rate' => '10', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        app(TaxRateRepository::class)->setDefault($this->business, 'sales', $vat);
        $this->business->forceFill(['tax_mode' => Workspace::TAXES_ENABLED, 'price_tax_mode' => 'exclusive'])->save();

        // With tax: the T-shirt at 11.00, 11.00 and 13.20, the mug at 8.80, the cup at 4.40.
        $this->getJson(self::STORE.'/facets')->assertJsonPath('data.price', ['min' => '4.40', 'max' => '13.20']);
        $this->assertSame(['Classic T-Shirt'], $this->shown('?price_min=11'));
        $this->assertSame(['Ceramic Mug', 'Cup'], $this->shown('?price_max=8.80&sort=title'));
    }
}
