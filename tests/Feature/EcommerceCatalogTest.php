<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Item;
use App\Models\User\User;
use App\Models\User\Workspace;
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
 * Doc 19 Phase B1: items published online (sections 19.8 and 19.9) - the
 * Catalog page of the items for sale with their online status, and an
 * item's listing: its link, descriptions, publication, visibility and dates,
 * sales method, availability, weight, videos and SEO, the storefronts it is
 * on, and its pictures - under Publish Products.
 */
class EcommerceCatalogTest extends TestCase
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
        Storage::fake('local');
        Storage::fake('public');

        $this->offerSalesLite();
        $ecommerce = Application::create(['app_key' => 'ecommerce', 'name' => 'eCommerce', 'family_key' => 'ecommerce', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 40, 'is_active' => true, 'status' => 'available']);
        $ecommerce->packages()->attach(Package::pluck('id'));
        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->business->package()->associate(Package::where('key', 'premium')->sole())->save();
        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();

        $kitchen = $this->business->itemCategories()->create(['name' => 'Kitchen']);
        $item    = fn (string $name, array $fields) => $this->items[$name] = $this->business->items()->create(['type' => 'physical', 'unit' => 'Piece', 'name' => $name, ...$fields]);
        $item('Ceramic Mug', ['usage' => 'sales', 'sale_price' => '8.00', 'sku' => 'MUG-1', 'category_id' => $kitchen->id]);
        $item('Black Tea', ['usage' => 'both', 'sale_price' => '3.50', 'sku' => 'TEA-1']);
        $item('Office Paper', ['usage' => 'purchase', 'sale_price' => '5.00']);
        $item('Old Lamp', ['usage' => 'sales', 'sale_price' => '20.00', 'is_active' => false]);
        $item('Custom Shelf', ['usage' => 'sales']);
    }

    /** $name's listing saved: published at its price, on every storefront, unless $fields say otherwise. */
    private function list(string $name, array $fields = []): TestResponse
    {
        return $this->putJson(self::CATALOG.'/'.$this->items[$name]->id, [
            'title' => $name, 'status' => 'published', 'visibility' => 'public', 'sales_method' => 'fixed_price', 'availability' => 'available', 'all_storefronts' => true, ...$fields,
        ]);
    }

    /** A member whose role holds $permissions. */
    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_the_items_for_sale_are_listed_with_their_online_status(): void
    {
        $names = fn (string $query = '') => array_column($this->getJson(self::CATALOG.$query)->assertOk()->json('data'), 'name');

        // Sold, or sold and bought - an item only bought is not for sale.
        $this->assertSame(['Black Tea', 'Ceramic Mug', 'Custom Shelf', 'Old Lamp'], $names());
        $this->getJson(self::CATALOG.'?search=MUG-1')->assertJsonPath('data.0.product', null)->assertJsonPath('data.0.category.name', 'Kitchen')->assertJsonPath('data.0.sale_price', '8.00');

        $this->list('Ceramic Mug', ['short_description' => ' A mug for tea. ', 'keywords' => 'cup, mug'])->assertOk()
            ->assertJsonPath('message', 'Online product saved.')
            ->assertJsonPath('data.product.title', 'Ceramic Mug')
            ->assertJsonPath('data.product.slug', 'ceramic-mug')
            ->assertJsonPath('data.product.short_description', 'A mug for tea.')
            ->assertJsonPath('data.product.status_now', 'published')
            ->assertJsonPath('data.item.sku', 'MUG-1');
        $this->assertTrue(ActivityLog::query()->where('description', 'Put the item "Ceramic Mug" in the catalog as "Ceramic Mug"')->exists());

        $this->assertSame(['Ceramic Mug'], $names('?status=published'));
        $this->assertSame(['Black Tea', 'Custom Shelf', 'Old Lamp'], $names('?status=offline'));
        $this->assertSame(['Black Tea'], $names('?search=tea'));
        $this->assertSame(['Ceramic Mug'], $names('?category_id='.$this->items['Ceramic Mug']->category_id));

        // A second listing of the same title gets a link of its own.
        $this->list('Black Tea', ['title' => 'Ceramic Mug', 'sales_method' => 'quotation'])->assertOk()->assertJsonPath('data.product.slug', 'ceramic-mug-2');
        $this->getJson(self::CATALOG.'/'.$this->items['Office Paper']->id)->assertNotFound()->assertJsonPath('message', 'There is no such item for sale.');
    }

    public function test_a_listing_is_checked_before_it_is_published(): void
    {
        $this->list('Ceramic Mug')->assertOk();

        // Its link: its own, written as a link.
        $this->list('Black Tea', ['slug' => 'Ceramic-Mug'])->assertUnprocessable()->assertJsonValidationErrors(['slug' => 'Another product has this link.']);
        $this->list('Black Tea', ['slug' => 'black tea'])->assertUnprocessable()->assertJsonValidationErrors(['slug' => 'Use letters, digits and single dashes - its link, such as ceramic-mug.']);

        // Published: an active item, priced when sold at its price - a draft may be either.
        $this->list('Old Lamp')->assertUnprocessable()->assertJsonValidationErrors(['status' => 'The item is switched off: switch it on in Items to publish it.']);
        $this->list('Old Lamp', ['status' => 'draft'])->assertOk();
        $this->list('Custom Shelf')->assertUnprocessable()->assertJsonValidationErrors(['sales_method' => 'The item has no sales price: give it one in Items, or sell it by quotation.']);
        $this->list('Custom Shelf', ['sales_method' => 'quotation'])->assertOk()->assertJsonPath('data.product.sales_method', 'quotation');

        // Scheduled: from a later day, ending no earlier.
        $tomorrow = now()->addDay()->toDateString();
        $this->list('Black Tea', ['status' => 'scheduled'])->assertUnprocessable()->assertJsonValidationErrors(['available_from' => 'A scheduled product starts on a later day: choose its first day.']);
        $this->list('Black Tea', ['status' => 'scheduled', 'available_from' => $tomorrow, 'available_until' => now()->toDateString()])->assertUnprocessable()
            ->assertJsonValidationErrors(['available_until' => 'It cannot end before it starts.']);
        $this->list('Black Tea', ['status' => 'scheduled', 'available_from' => $tomorrow])->assertOk()->assertJsonPath('data.product.status_now', 'scheduled');
        $this->assertSame(['Black Tea'], array_column($this->getJson(self::CATALOG.'?status=scheduled')->json('data'), 'name'));

        // Limited: how many are left.
        $this->list('Ceramic Mug', ['availability' => 'limited'])->assertUnprocessable()->assertJsonValidationErrors(['stock_quantity' => 'Say how many are left to sell.']);
        $this->list('Ceramic Mug', ['availability' => 'limited', 'stock_quantity' => '12', 'weight' => '0.35', 'weight_unit' => 'kg'])->assertOk()
            ->assertJsonPath('data.product.stock_quantity', '12.000')
            ->assertJsonPath('data.product.weight', '0.350');

        // Its storefronts: every one, or some of the business's own.
        $location = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $shop     = $this->business->storefronts()->create(['name' => 'Main Shop', 'code' => 'main-shop', 'default_language' => 'en', 'languages' => ['en'], 'currency' => 'USD', 'currencies' => ['USD'], 'countries' => ['BD'], 'location_id' => $location->id]);
        $theirs   = $this->businessOf(User::factory()->create());
        $elsewhere = $theirs->storefronts()->create(['name' => 'Other', 'code' => 'other-shop', 'default_language' => 'en', 'languages' => ['en'], 'currency' => 'USD', 'currencies' => ['USD'], 'countries' => ['BD'], 'location_id' => $theirs->locations()->create(['user_id' => $theirs->user_id, 'name' => 'Sylhet', 'code' => 'SYL'])->id]);
        $this->list('Ceramic Mug', ['all_storefronts' => false])->assertUnprocessable()->assertJsonValidationErrors(['storefront_ids' => 'Choose the storefronts it is on.']);
        $this->list('Ceramic Mug', ['all_storefronts' => false, 'storefront_ids' => [$elsewhere->id]])->assertUnprocessable()->assertJsonValidationErrors('storefront_ids');
        $this->list('Ceramic Mug', ['all_storefronts' => false, 'storefront_ids' => [$shop->id]])->assertOk()->assertJsonPath('data.product.storefront_ids', [$shop->id])
            ->assertJsonPath('data.storefronts.0.name', 'Main Shop');

        // Its full description keeps the editor's formatting alone; its videos are https links.
        $saved = $this->list('Ceramic Mug', ['description' => '<p>Hand <strong>made</strong><script>alert(1)</script></p>', 'video_urls' => ['https://www.youtube.com/watch?v=abc']])->assertOk()->json('data.product');
        $this->assertStringContainsString('<strong>made</strong>', $saved['description']);
        $this->assertStringNotContainsString('<script', $saved['description']);
        $this->list('Ceramic Mug', ['video_urls' => ['http://example.com/video']])->assertUnprocessable()->assertJsonValidationErrors(['video_urls.0' => "Give the video's https:// link."]);
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the online product "Ceramic Mug"')->exists());

        // Taken out, its item stays as it was.
        $this->deleteJson(self::CATALOG.'/'.$this->items['Ceramic Mug']->id)->assertOk()->assertJsonPath('message', '"Ceramic Mug" taken out of the catalog.');
        $this->getJson(self::CATALOG.'/'.$this->items['Ceramic Mug']->id)->assertOk()->assertJsonPath('data.product', null)->assertJsonPath('data.item.name', 'Ceramic Mug');
        $this->assertTrue(Item::whereKey($this->items['Ceramic Mug']->id)->exists());
    }

    public function test_a_listing_keeps_its_pictures(): void
    {
        $pictures = self::CATALOG.'/'.$this->items['Ceramic Mug']->id.'/pictures';
        $upload   = fn (int $count) => $this->post($pictures, ['files' => array_map(fn (int $n) => UploadedFile::fake()->image("mug-{$n}.jpg", 800, 600), range(1, $count))], ['Accept' => 'application/json']);

        $upload(1)->assertNotFound()->assertJsonPath('message', 'This item is not in the catalog.');
        $this->list('Ceramic Mug', ['status' => 'draft'])->assertOk();

        $upload(2)->assertCreated()->assertJsonPath('message', '2 pictures added.')->assertJsonCount(2, 'data');
        $shown = $this->getJson(self::CATALOG.'/'.$this->items['Ceramic Mug']->id)->json('data.product.pictures');
        $this->assertCount(2, $shown);
        $this->assertNotNull($shown[0]['url']);
        $this->assertNotNull($shown[0]['thumbnail_url']);
        $this->assertTrue(ActivityLog::query()->where('description', 'Added picture "mug-1.jpg" to online product "Ceramic Mug"')->exists());
        // Its item has no image of its own: the Catalog page shows its first picture.
        $this->assertSame($shown[0]['thumbnail_url'], $this->getJson(self::CATALOG.'?search=MUG-1')->json('data.0.thumbnail_url'));

        $upload(11)->assertJsonValidationErrors(['files' => 'Upload up to ten files at a time.']);
        $upload(9)->assertCreated();
        $upload(2)->assertUnprocessable()->assertJsonValidationErrors(['files' => 'A product keeps up to 12 pictures: remove some first.']);

        $this->deleteJson("{$pictures}/{$shown[0]['id']}")->assertOk()->assertJsonPath('message', 'Picture removed.');
        $this->assertCount(10, $this->getJson(self::CATALOG.'/'.$this->items['Ceramic Mug']->id)->json('data.product.pictures'));
    }

    public function test_the_catalog_is_seen_with_eCommerce_and_items_and_changed_under_publish_products(): void
    {
        $this->assertEqualsCanonicalizing(['ecommerce.catalog.view', 'ecommerce.store.view', 'items.view'], Permissions::expand(['ecommerce.catalog.view']));
        $this->assertEqualsCanonicalizing(['ecommerce.catalog.publish', 'ecommerce.catalog.view', 'ecommerce.store.view', 'items.view'], Permissions::expand(['ecommerce.catalog.publish']));

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.catalog.view'])));
        $this->getJson(self::CATALOG)->assertOk();
        $this->list('Ceramic Mug')->assertForbidden();

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.catalog.publish'])));
        $this->list('Ceramic Mug')->assertOk();

        Sanctum::actingAs($this->member(['ecommerce.store.view']));
        $this->getJson(self::CATALOG)->assertForbidden();
    }
}
