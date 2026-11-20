<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\CatalogProductVariant;
use App\Models\User\Item;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase B3: product options and variants (section 19.10) - a
 * listing's options, and the existing items it groups as its variants, its
 * own item first - under Publish Products.
 */
class EcommerceCatalogVariantsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const CATALOG = '/api/v1/ecommerce/catalog';

    private User $user;

    private Workspace $business;

    /** The items by name. */
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $ecommerce = Application::create(['app_key' => 'ecommerce', 'name' => 'eCommerce', 'family_key' => 'ecommerce', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 40, 'is_active' => true, 'status' => 'available']);
        $ecommerce->packages()->attach(Package::pluck('id'));
        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->package()->associate(Package::where('key', 'premium')->sole())->save();
        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();

        foreach ([
            'T-Shirt S'  => ['sale_price' => '10.00', 'sku' => 'TS-S'],
            'T-Shirt M'  => ['sale_price' => '10.00', 'sku' => 'TS-M'],
            'T-Shirt L'  => ['sale_price' => '12.00', 'sku' => 'TS-L'],
            'T-Shirt XL' => ['sale_price' => null],
            'Cap'        => ['sale_price' => '6.00'],
            'Mug'        => ['sale_price' => '8.00'],
            'Ebook'      => ['sale_price' => '5.00', 'type' => 'digital'],
            'Paper'      => ['sale_price' => null, 'usage' => 'purchase'],
        ] as $name => $fields) {
            $this->items[$name] = $this->business->items()->create(['type' => 'physical', 'unit' => 'Piece', 'usage' => 'sales', 'name' => $name, ...$fields]);
        }

        $this->list('T-Shirt S')->assertOk();
        $this->list('Mug')->assertOk();
    }

    private function list(string $name, array $fields = []): TestResponse
    {
        return $this->putJson(self::CATALOG.'/'.$this->items[$name]->id, ['title' => $name, 'status' => 'draft', 'visibility' => 'public', 'sales_method' => 'fixed_price', 'availability' => 'available', 'all_storefronts' => true, ...$fields]);
    }

    private function variant(string $name, array $values, array $fields = []): array
    {
        return ['item_id' => $this->items[$name]->id, 'option_values' => $values, 'status' => 'active', 'availability' => 'available', ...$fields];
    }

    private function save(string $name, array $options, array $variants): TestResponse
    {
        return $this->putJson(self::CATALOG.'/'.$this->items[$name]->id.'/variants', ['options' => $options, 'variants' => $variants]);
    }

    private function sizes(array $variants = []): TestResponse
    {
        return $this->save('T-Shirt S', [['name' => 'Size', 'values' => ['S', 'M', 'L']]], $variants ?: [
            $this->variant('T-Shirt S', ['S']),
            $this->variant('T-Shirt M', ['m']),
            $this->variant('T-Shirt L', ['L'], ['availability' => 'limited', 'stock_quantity' => 5, 'weight' => '0.3']),
        ]);
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_a_listing_groups_items_as_its_variants_by_its_options(): void
    {
        $own = $this->items['T-Shirt S']->id;

        // Its own item, and items for sale of its type in no listing.
        $this->getJson(self::CATALOG."/{$own}/variant-items")->assertOk()
            ->assertJsonPath('data.*.name', ['Cap', 'T-Shirt L', 'T-Shirt M', 'T-Shirt S', 'T-Shirt XL']);

        $this->sizes()->assertOk()
            ->assertJsonPath('message', 'Options and variants saved.')
            ->assertJsonPath('data.product.options', [['name' => 'Size', 'values' => ['S', 'M', 'L']]])
            ->assertJsonPath('data.product.variants.*.item.sku', ['TS-S', 'TS-M', 'TS-L'])
            ->assertJsonPath('data.product.variants.*.option_values', [['S'], ['M'], ['L']])
            ->assertJsonPath('data.product.variants.2.stock_quantity', '5.000')
            ->assertJsonPath('data.product.variants.2.weight', '0.300');
        $this->assertTrue(ActivityLog::query()->where('description', 'Gave the online product "T-Shirt S" options and variants')->exists());

        // Its variants are online as its: the Catalog page says so, and their pages are the listing's.
        $rows = collect($this->getJson(self::CATALOG)->json('data'))->keyBy('name');
        $this->assertSame(['item_id' => $own, 'title' => 'T-Shirt S'], $rows['T-Shirt M']['variant_of']);
        $this->assertNull($rows['T-Shirt S']['variant_of']);
        $this->assertSame(3, $rows['T-Shirt S']['product']['variant_count']);
        $this->assertSame(0, $rows['Mug']['product']['variant_count']);
        $this->assertSame(['Cap', 'Ebook', 'T-Shirt XL'], collect($this->getJson(self::CATALOG.'?status=offline')->json('data'))->pluck('name')->all());
        $this->getJson(self::CATALOG.'/'.$this->items['T-Shirt M']->id)->assertOk()
            ->assertJsonPath('data.variant_of', ['item_id' => $own, 'title' => 'T-Shirt S'])
            ->assertJsonPath('data.product', null);
        $this->list('T-Shirt M')->assertStatus(409)->assertJsonPath('message', 'This item is a variant of "T-Shirt S": change it on that product\'s page.');

        // Reordered, one taken out and another put in.
        $this->sizes([$this->variant('T-Shirt L', ['L']), $this->variant('T-Shirt S', ['S']), $this->variant('Cap', ['M'])])->assertOk()
            ->assertJsonPath('data.product.variants.*.item.name', ['T-Shirt L', 'T-Shirt S', 'Cap']);
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the options and variants of the online product "T-Shirt S"')->exists());
        $this->assertNull($this->getJson(self::CATALOG.'/'.$this->items['T-Shirt M']->id)->json('data.variant_of'));
    }

    public function test_the_options_and_variants_are_checked(): void
    {
        $sizes = [['name' => 'Size', 'values' => ['S', 'M', 'L']]];

        $this->save('T-Shirt S', [['name' => 'Size', 'values' => ['S']], ['name' => ' size ', 'values' => ['M']]], [])->assertJsonValidationErrors(['options.1.name' => 'Another option has this name.']);
        $this->save('T-Shirt S', [['name' => 'Size', 'values' => ['S', 's']]], [])->assertJsonValidationErrors(['options.0.values' => 'Give each value once.']);
        $this->save('T-Shirt S', [], [$this->variant('T-Shirt S', ['S'])])->assertJsonValidationErrors(['variants' => 'Name its options first: without them it has no variants.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt M', ['M'])])->assertJsonValidationErrors(['variants' => 'Its own item, "T-Shirt S", is one of its variants.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), ['item_id' => null, 'option_values' => ['M'], 'status' => 'active', 'availability' => 'available']])->assertJsonValidationErrors(['variants.1.item_id' => 'Choose its item.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('T-Shirt M', ['XL'])])->assertJsonValidationErrors(['variants.1.option_values' => 'Choose a value of each option.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('T-Shirt M', ['s'])])->assertJsonValidationErrors(['variants.1.option_values' => 'Another variant has these options.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('Ebook', ['M'])])->assertJsonValidationErrors(['variants.1.item_id' => 'A variant is an item of the same type as its own item.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('Mug', ['M'])])->assertJsonValidationErrors(['variants.1.item_id' => 'This item has a listing of its own: take it out of the catalog first.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('Paper', ['M'])])->assertJsonValidationErrors(['variants.1.item_id' => 'Choose among your items for sale.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('T-Shirt M', ['M'], ['availability' => 'limited'])])->assertJsonValidationErrors(['variants.1.stock_quantity' => 'Say how many are left to sell.']);

        // Another listing's variant is not this one's.
        $this->save('Mug', [['name' => 'Size', 'values' => ['Small', 'Large']]], [$this->variant('Mug', ['Small']), $this->variant('Cap', ['Large'])])->assertOk();
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('Cap', ['M'])])->assertJsonValidationErrors(['variants.1.item_id' => 'This item is a variant of "Mug".']);

        // Published, a variant shown is of an active item, priced when sold at its price; hidden, it need not be.
        $this->list('T-Shirt S', ['status' => 'published'])->assertOk();
        $this->items['T-Shirt M']->update(['is_active' => false]);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('T-Shirt M', ['M'])])->assertJsonValidationErrors(['variants.1.item_id' => 'The item is switched off: switch it on in Items, or hide the variant.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('T-Shirt XL', ['L'])])->assertJsonValidationErrors(['variants.1.item_id' => 'The item has no sales price: give it one in Items, or hide the variant.']);
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('T-Shirt M', ['M'], ['status' => 'hidden']), $this->variant('T-Shirt XL', ['L'], ['status' => 'hidden'])])->assertOk();

        // And the listing published again with a variant shown that is not sellable.
        $this->list('T-Shirt S', ['status' => 'draft'])->assertOk();
        $this->save('T-Shirt S', $sizes, [$this->variant('T-Shirt S', ['S']), $this->variant('T-Shirt XL', ['L'])])->assertOk();
        $this->list('T-Shirt S', ['status' => 'published'])->assertJsonValidationErrors(['sales_method' => 'The variant "T-Shirt XL" has no sales price: give it one in Items, or hide the variant.']);
        $this->list('T-Shirt S', ['status' => 'published', 'sales_method' => 'quotation'])->assertOk();
    }

    public function test_variants_go_with_their_options_their_listing_or_their_item(): void
    {
        $this->sizes()->assertOk();

        $this->save('T-Shirt S', [], [])->assertOk()->assertJsonPath('message', 'Options removed.')
            ->assertJsonPath('data.product.options', [])
            ->assertJsonPath('data.product.variants', []);
        $this->assertSame(0, CatalogProductVariant::count());
        $this->assertTrue(ActivityLog::query()->where('description', 'Removed the options and variants of the online product "T-Shirt S"')->exists());

        $this->sizes()->assertOk();
        $this->deleteJson('/api/v1/items/'.$this->items['T-Shirt L']->id)->assertOk();
        $this->assertSame(['T-Shirt S', 'T-Shirt M'], $this->getJson(self::CATALOG.'/'.$this->items['T-Shirt S']->id)->json('data.product.variants.*.item.name'));

        $this->deleteJson(self::CATALOG.'/'.$this->items['T-Shirt S']->id)->assertOk();
        $this->assertSame(0, CatalogProductVariant::count());
        $this->assertSame(['Cap', 'Ebook', 'T-Shirt M', 'T-Shirt S', 'T-Shirt XL'], collect($this->getJson(self::CATALOG.'?status=offline')->json('data'))->pluck('name')->all());
        $this->assertTrue(Item::whereKey($this->items['T-Shirt M']->id)->exists());
    }

    public function test_options_and_variants_are_seen_with_the_catalog_and_changed_under_publish_products(): void
    {
        $this->sizes()->assertOk();
        $own = $this->items['T-Shirt S']->id;

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.catalog.view'])));
        $this->getJson(self::CATALOG."/{$own}")->assertOk()->assertJsonCount(3, 'data.product.variants');
        $this->getJson(self::CATALOG."/{$own}/variant-items")->assertForbidden();
        $this->sizes()->assertForbidden();

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.catalog.publish'])));
        $this->getJson(self::CATALOG."/{$own}/variant-items")->assertOk();
        $this->sizes()->assertOk();
    }
}
