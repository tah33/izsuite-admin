<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\CatalogCategory;
use App\Models\User\CatalogProduct;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase B2: categories, collections and filters (section 19.11) - an
 * item category's online side, the business's collections of chosen
 * products or of a brand's, and a listing's custom attributes - under
 * Manage Categories and Collections.
 */
class EcommerceCatalogTaxonomyTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    /** The item categories and listings by name. */
    private array $categories = [];

    private array $listings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Storage::fake('public');

        $this->offerSalesLite();
        $ecommerce = Application::create(['app_key' => 'ecommerce', 'name' => 'eCommerce', 'family_key' => 'ecommerce', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 40, 'is_active' => true, 'status' => 'available']);
        $ecommerce->packages()->attach(Package::pluck('id'));
        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->package()->associate(Package::where('key', 'premium')->sole())->save();
        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();

        $this->categories = ['Kitchen' => $this->business->itemCategories()->create(['name' => 'Kitchen']), 'Drinks' => $this->business->itemCategories()->create(['name' => 'Drinks'])];
        $brand            = $this->business->itemBrands()->create(['name' => 'Rahman']);
        $item             = fn (string $name, array $fields) => $this->business->items()->create(['type' => 'physical', 'unit' => 'Piece', 'usage' => 'sales', 'name' => $name, ...$fields]);
        $listed           = [
            'Ceramic Mug' => $item('Ceramic Mug', ['sale_price' => '8.00', 'category_id' => $this->categories['Kitchen']->id]),
            'Black Tea'   => $item('Black Tea', ['sale_price' => '3.50', 'category_id' => $this->categories['Drinks']->id, 'brand_id' => $brand->id]),
            'Green Tea'   => $item('Green Tea', ['sale_price' => '4.00', 'brand_id' => $brand->id]),
        ];
        $item('Biscuits', ['sale_price' => '1.20', 'category_id' => $this->categories['Drinks']->id]);

        foreach ($listed as $name => $listing) {
            $this->listings[$name] = $this->list($listing->id)->assertOk()->json('data.product.id');
        }
    }

    private function list(int $itemId, array $fields = []): TestResponse
    {
        return $this->putJson("/api/v1/ecommerce/catalog/{$itemId}", ['title' => 'Listing '.$itemId, 'status' => 'draft', 'visibility' => 'public', 'sales_method' => 'fixed_price', 'availability' => 'available', 'all_storefronts' => true, ...$fields]);
    }

    private function collection(array $fields = []): TestResponse
    {
        return $this->postJson('/api/v1/ecommerce/collections', ['name' => 'Summer Sale', 'kind' => 'promotion', 'status' => 'active', 'product_ids' => [$this->listings['Black Tea'], $this->listings['Ceramic Mug']], ...$fields]);
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_an_item_category_is_given_its_online_side(): void
    {
        $kitchen = $this->categories['Kitchen']->id;
        $drinks  = $this->categories['Drinks']->id;

        $this->getJson('/api/v1/ecommerce/catalog-categories')->assertOk()
            ->assertJsonPath('data.*.name', ['Drinks', 'Kitchen'])
            ->assertJsonPath('data.*.listed_count', [1, 1])
            ->assertJsonPath('data.*.online', [null, null]);

        $this->putJson("/api/v1/ecommerce/catalog-categories/{$kitchen}", ['in_menu' => true, 'position' => 2, 'description' => '<p>For <em>cooks</em></p>', 'seo_title' => ' Kitchen things '])->assertOk()
            ->assertJsonPath('message', 'Online category saved.')
            ->assertJsonPath('data.online.slug', 'kitchen')
            ->assertJsonPath('data.online.description', '<p>For <em>cooks</em></p>')
            ->assertJsonPath('data.online.seo_title', 'Kitchen things')
            ->assertJsonPath('data.online.position', 2);
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the online category "Kitchen"')->exists());

        $this->putJson("/api/v1/ecommerce/catalog-categories/{$drinks}", ['slug' => 'Kitchen', 'in_menu' => false])->assertUnprocessable()->assertJsonValidationErrors(['slug' => 'Another category has this link.']);

        // Its picture: given before its online side, which it then starts.
        $this->post("/api/v1/ecommerce/catalog-categories/{$drinks}/image", ['image' => UploadedFile::fake()->image('drinks.png', 600, 400)], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('message', 'Picture saved.')
            ->assertJsonPath('data.online.slug', 'drinks');
        $this->assertNotNull($this->getJson('/api/v1/ecommerce/catalog-categories')->json('data.0.online.image_url'));
        $this->deleteJson("/api/v1/ecommerce/catalog-categories/{$drinks}/image")->assertOk()->assertJsonPath('data.online.image_url', null);
        $this->deleteJson("/api/v1/ecommerce/catalog-categories/{$drinks}/image")->assertNotFound()->assertJsonPath('message', 'This category has no picture.');
    }

    public function test_collections_hold_chosen_products_or_a_brands(): void
    {
        $this->getJson('/api/v1/ecommerce/collections/options')->assertOk()
            ->assertJsonPath('data.products.*.title', ['Listing '.CatalogProduct::find($this->listings['Ceramic Mug'])->item_id, 'Listing '.CatalogProduct::find($this->listings['Black Tea'])->item_id, 'Listing '.CatalogProduct::find($this->listings['Green Tea'])->item_id])
            ->assertJsonPath('data.brands', []);

        $id = $this->collection(['available_from' => '2026-12-01', 'available_until' => '2026-12-31', 'description' => '<p>Up to <strong>20%</strong> off</p>'])->assertCreated()
            ->assertJsonPath('message', 'Collection added.')
            ->assertJsonPath('data.slug', 'summer-sale')
            ->assertJsonPath('data.products.*.id', [$this->listings['Black Tea'], $this->listings['Ceramic Mug']])
            ->assertJsonPath('data.product_count', 2)
            ->json('data.id');
        $this->assertTrue(ActivityLog::query()->where('description', 'Added the collection "Summer Sale"')->exists());

        // Its name and link its own; its days in order; its products the catalog's.
        $this->collection()->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Another collection has this name.']);
        $this->collection(['name' => 'Winter', 'slug' => 'summer-sale'])->assertUnprocessable()->assertJsonValidationErrors(['slug' => 'Another collection has this link.']);
        $this->collection(['name' => 'Winter', 'available_from' => '2026-12-31', 'available_until' => '2026-12-01'])->assertUnprocessable()->assertJsonValidationErrors(['available_until' => 'It cannot end before it starts.']);
        $this->collection(['name' => 'Winter', 'product_ids' => [999999]])->assertUnprocessable()->assertJsonValidationErrors(['product_ids' => 'Choose among the products in your catalog.']);

        // Reordered.
        $this->putJson("/api/v1/ecommerce/collections/{$id}", ['name' => 'Summer Sale', 'kind' => 'season', 'status' => 'hidden', 'product_ids' => [$this->listings['Ceramic Mug'], $this->listings['Black Tea']]])->assertOk()
            ->assertJsonPath('data.products.*.id', [$this->listings['Ceramic Mug'], $this->listings['Black Tea']])
            ->assertJsonPath('data.status', 'hidden');
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the collection "Summer Sale"')->exists());

        // A brand's: every listed product of it, while the business keeps brands.
        $brand = $this->business->itemBrands()->sole();
        $this->collection(['name' => 'Rahman Teas', 'kind' => 'brand', 'item_brand_id' => $brand->id])->assertUnprocessable()->assertJsonValidationErrors(['item_brand_id' => 'Brands are off: switch them on in Settings → Items.']);
        $this->business->forceFill(['brands_enabled' => true])->save();
        $this->getJson('/api/v1/ecommerce/collections/options')->assertJsonPath('data.brands', [['id' => $brand->id, 'name' => 'Rahman']]);
        $this->collection(['name' => 'Rahman Teas', 'kind' => 'brand', 'item_brand_id' => $brand->id])->assertCreated()->assertJsonPath('data.products', []);
        $this->assertSame(['Rahman Teas' => 2, 'Summer Sale' => 2], collect($this->getJson('/api/v1/ecommerce/collections')->json('data'))->pluck('product_count', 'name')->sortKeys()->all());

        $this->deleteJson("/api/v1/ecommerce/collections/{$id}")->assertOk()->assertJsonPath('message', 'Collection "Summer Sale" deleted.');
        $this->assertSame(3, CatalogProduct::count());
    }

    public function test_a_listing_keeps_its_custom_attributes(): void
    {
        $item = CatalogProduct::find($this->listings['Ceramic Mug'])->item_id;

        $this->list($item, ['custom_attributes' => [['name' => 'Material', 'value' => 'Stoneware'], ['name' => ' material ', 'value' => 'Clay'], ['name' => 'Capacity', 'value' => ' 350 ml ']]])->assertOk()
            ->assertJsonPath('data.product.custom_attributes', [['name' => 'Material', 'value' => 'Stoneware'], ['name' => 'Capacity', 'value' => '350 ml']]);
        $this->list($item, ['custom_attributes' => [['name' => 'Colour', 'value' => '']]])->assertUnprocessable()->assertJsonValidationErrors(['custom_attributes.0.value' => 'Give the attribute its value.']);
    }

    public function test_a_deleted_item_category_or_item_takes_its_online_pictures_and_a_deleted_brand_leaves_its_collection(): void
    {
        $drinks = $this->categories['Drinks']->id;
        $mug    = CatalogProduct::findOrFail($this->listings['Ceramic Mug']);

        $this->post("/api/v1/ecommerce/catalog-categories/{$drinks}/image", ['image' => UploadedFile::fake()->image('drinks.png', 600, 400)], ['Accept' => 'application/json'])->assertOk();
        $this->post("/api/v1/ecommerce/catalog/{$mug->item_id}/pictures", ['files' => [UploadedFile::fake()->image('mug.jpg', 800, 600)]], ['Accept' => 'application/json'])->assertCreated();
        $stored = WorkspaceFile::query()->get()->flatMap(fn (WorkspaceFile $file) => [$file->path, $file->thumbnail_path])->filter()->values();
        $this->assertCount(4, $stored);

        $this->deleteJson("/api/v1/item-categories/{$drinks}")->assertOk();
        $this->deleteJson("/api/v1/items/{$mug->item_id}")->assertOk();

        $this->assertSame(0, WorkspaceFile::count());
        $stored->each(fn (string $path) => Storage::disk('public')->assertMissing($path));
        $this->assertSame(0, CatalogCategory::count());
        $this->assertNull(CatalogProduct::find($mug->id));

        // A brand's collection is kept, without its brand, to be given another or made another kind.
        $this->business->forceFill(['brands_enabled' => true])->save();
        $brand = $this->business->itemBrands()->sole();
        $id    = $this->collection(['name' => 'Rahman Teas', 'kind' => 'brand', 'item_brand_id' => $brand->id])->assertCreated()->json('data.id');
        $this->deleteJson("/api/v1/item-brands/{$brand->id}")->assertOk();
        $this->getJson('/api/v1/ecommerce/collections')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.brand', null)->assertJsonPath('data.0.product_count', 0);
    }

    public function test_categories_and_collections_are_seen_with_the_catalog_and_changed_under_their_permission(): void
    {
        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.catalog.view'])));
        $this->getJson('/api/v1/ecommerce/catalog-categories')->assertOk();
        $this->getJson('/api/v1/ecommerce/collections')->assertOk();
        $this->putJson('/api/v1/ecommerce/catalog-categories/'.$this->categories['Kitchen']->id, ['in_menu' => true])->assertForbidden();
        $this->collection()->assertForbidden();

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.catalog.manage'])));
        $this->putJson('/api/v1/ecommerce/catalog-categories/'.$this->categories['Kitchen']->id, ['in_menu' => true])->assertOk();
        $this->collection()->assertCreated();
    }
}
