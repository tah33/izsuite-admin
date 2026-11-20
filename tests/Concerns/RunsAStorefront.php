<?php

namespace Tests\Concerns;

use App\Models\Admin\Language;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\CatalogProduct;
use App\Models\User\ItemCategory;
use App\Models\User\Storefront;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CatalogProductRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

/**
 * A business with a storefront open to its customers (Doc 19 Phase C): on
 * Premium in dollars, with eCommerce on and brands kept, Kitchen in its
 * storefront's menu, the brand Rahman, a live storefront `rahman-shop`, and
 * the items asked for. For a test using OffersSalesLite and
 * SignsIntoBusinesses.
 */
trait RunsAStorefront
{
    protected const STORE = '/api/v1/stores/rahman-shop';

    protected User $user;

    protected Workspace $business;

    protected Storefront $store;

    protected ItemCategory $kitchen;

    /** The items by name. */
    protected array $items = [];

    /** @param array<string, array> $items each item's fields by its name: a physical item sold, unless they say otherwise */
    protected function openStorefront(array $items): void
    {
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $ecommerce = Application::create(['app_key' => 'ecommerce', 'name' => 'eCommerce', 'family_key' => 'ecommerce', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 40, 'is_active' => true, 'status' => 'available']);
        $ecommerce->packages()->attach(Package::pluck('id'));
        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'brands_enabled' => true])->save();
        $this->business->package()->associate(Package::where('key', 'premium')->sole())->save();
        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();

        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->postJson('/api/v1/ecommerce/storefronts', [
            'name' => 'Rahman Shop', 'code' => 'rahman-shop', 'status' => 'live', 'default_language' => 'en', 'currency' => 'USD', 'countries' => ['BD'], 'location_id' => $dhaka->id, 'support_email' => 'help@rahman.test',
        ])->assertCreated();
        $this->store = Storefront::sole();

        $this->kitchen = $this->business->itemCategories()->create(['name' => 'Kitchen']);
        $rahman        = $this->business->itemBrands()->create(['name' => 'Rahman']);
        $this->putJson("/api/v1/ecommerce/catalog-categories/{$this->kitchen->id}", ['in_menu' => true, 'position' => 1])->assertOk();

        // 'in_kitchen' and 'by_rahman' put an item in Kitchen and give it the brand.
        foreach ($items as $name => $fields) {
            $placed = [
                ...array_diff_key($fields, ['in_kitchen' => true, 'by_rahman' => true]),
                ...(($fields['in_kitchen'] ?? false) ? ['category_id' => $this->kitchen->id] : []),
                ...(($fields['by_rahman'] ?? false) ? ['brand_id' => $rahman->id] : []),
            ];
            $this->items[$name] = $this->business->items()->create(['type' => 'physical', 'unit' => 'Piece', 'usage' => 'sales', 'name' => $name, ...$placed]);
        }
    }

    /** $name's item listed straight through the repository - published unless $fields say otherwise - so any state can be made. */
    protected function listed(string $name, array $fields = [], array $storefrontIds = []): CatalogProduct
    {
        return app(CatalogProductRepository::class)->create($this->business, $this->items[$name], [
            'title' => $name, 'slug' => Str::slug($name), 'status' => 'published', ...$fields,
        ], $storefrontIds);
    }

    /** The titles of the store's products for $query. */
    protected function shown(string $query = ''): array
    {
        return collect($this->getJson(self::STORE.'/products'.$query)->assertOk()->json('data'))->pluck('title')->all();
    }
}
