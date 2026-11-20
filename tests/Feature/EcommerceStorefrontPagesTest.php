<?php

namespace Tests\Feature;

use App\Models\User\CatalogCollection;
use App\Models\User\CatalogProduct;
use App\Models\User\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase C2: a storefront's category and collection pages (sections
 * 19.6 and 19.11) and its search's suggestions (19.12) - a category's online
 * side by its link, the collections it shows with their products in their
 * order or the brand's, and the products, categories and collections a
 * customer's typing names.
 */
class EcommerceStorefrontPagesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront([
            'Ceramic Mug' => ['sale_price' => '8.00', 'in_kitchen' => true],
            'Tea Pot'     => ['sale_price' => '20.00', 'in_kitchen' => true],
            'Black Tea'   => ['sale_price' => '3.50', 'by_rahman' => true],
            'Green Tea'   => ['sale_price' => '4.00', 'by_rahman' => true],
        ]);

        foreach (array_keys($this->items) as $name) {
            $this->listed($name);
        }
    }

    private function collection(string $name, array $fields = []): CatalogCollection
    {
        $products = $fields['products'] ?? [];
        unset($fields['products']);

        $collection = (new CatalogCollection(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'kind' => 'promotion', ...$fields]))->forceFill(['workspace_id' => $this->business->id]);
        $collection->save();

        foreach (array_values($products) as $position => $title) {
            $collection->products()->attach(CatalogProduct::where('title', $title)->sole()->id, ['position' => $position]);
        }

        return $collection;
    }

    public function test_a_category_has_its_page_with_its_products(): void
    {
        $this->putJson("/api/v1/ecommerce/catalog-categories/{$this->kitchen->id}", ['in_menu' => true, 'description' => '<p>For <em>cooks</em></p>', 'seo_title' => 'Kitchenware'])->assertOk();

        $this->getJson(self::STORE.'/categories/kitchen')->assertOk()->assertExactJson(['data' => [
            'name' => 'Kitchen', 'slug' => 'kitchen', 'description' => '<p>For <em>cooks</em></p>', 'image' => null, 'seo' => ['title' => 'Kitchenware', 'description' => null],
        ]]);
        $this->assertSame(['Ceramic Mug', 'Tea Pot'], $this->shown('?category=kitchen&sort=title'));
        $this->getJson(self::STORE.'/categories/garden')->assertNotFound()->assertJsonPath('message', 'There is no such category in this store.');
    }

    public function test_a_collection_shows_its_products_in_its_order_or_its_brands(): void
    {
        $this->collection('Summer Sale', ['products' => ['Tea Pot', 'Black Tea', 'Ceramic Mug'], 'position' => 2, 'description' => '<p>Up to 20% off</p>']);
        $this->collection('Rahman Teas', ['kind' => 'brand', 'item_brand_id' => $this->items['Black Tea']->brand_id, 'position' => 1]);
        $this->collection('Hidden One', ['status' => 'hidden']);
        $this->collection('Next Year', ['available_from' => now()->addYear()->toDateString()]);
        $this->collection('Last Year', ['available_until' => now()->subYear()->toDateString()]);

        $this->getJson(self::STORE.'/collections')->assertOk()->assertJsonPath('data.*.name', ['Rahman Teas', 'Summer Sale'])->assertJsonMissingPath('data.0.description');
        $this->getJson(self::STORE.'/collections/summer-sale')->assertOk()
            ->assertJsonPath('data.description', '<p>Up to 20% off</p>')
            ->assertJsonPath('data.seo', ['title' => 'Summer Sale', 'description' => null]);

        // In its order, unless sorted; one no longer shown drops out.
        $this->assertSame(['Tea Pot', 'Black Tea', 'Ceramic Mug'], $this->shown('?collection=summer-sale'));
        $this->assertSame(['Black Tea', 'Ceramic Mug', 'Tea Pot'], $this->shown('?collection=summer-sale&sort=price_low'));
        CatalogProduct::where('title', 'Black Tea')->update(['status' => 'hidden']);
        $this->assertSame(['Tea Pot', 'Ceramic Mug'], $this->shown('?collection=summer-sale'));
        $this->assertSame(['Green Tea'], $this->shown('?collection=rahman-teas'));

        foreach (['hidden-one', 'next-year', 'last-year', 'nothing'] as $slug) {
            $this->getJson(self::STORE."/collections/{$slug}")->assertNotFound()->assertJsonPath('message', 'There is no such collection in this store.');
            $this->getJson(self::STORE."/products?collection={$slug}")->assertNotFound();
        }
    }

    public function test_the_search_suggests_products_categories_and_collections(): void
    {
        $this->collection('Rahman Teas', ['kind' => 'brand', 'item_brand_id' => $this->items['Black Tea']->brand_id]);

        $this->getJson(self::STORE.'/suggestions?search=tea')->assertOk()
            ->assertJsonPath('data.products.*.title', ['Green Tea', 'Black Tea', 'Tea Pot'])
            ->assertJsonPath('data.products.0.image_url', null)
            ->assertJsonPath('data.categories', [])
            ->assertJsonPath('data.collections', [['name' => 'Rahman Teas', 'slug' => 'rahman-teas']]);
        $this->getJson(self::STORE.'/suggestions?search=kit')->assertOk()
            ->assertJsonPath('data.products.*.title', ['Tea Pot', 'Ceramic Mug'])
            ->assertJsonPath('data.categories', [['name' => 'Kitchen', 'slug' => 'kitchen']]);
        $this->getJson(self::STORE.'/suggestions?search=t')->assertJsonValidationErrors('search');

        // A store not open suggests nothing, and shows no category or collection.
        $this->store->update(['status' => Storefront::DRAFT]);
        $this->getJson(self::STORE.'/suggestions?search=tea')->assertNotFound()->assertJsonPath('message', 'This store is not open.');
        $this->getJson(self::STORE.'/categories/kitchen')->assertNotFound();
        $this->getJson(self::STORE.'/collections')->assertNotFound();
    }
}
