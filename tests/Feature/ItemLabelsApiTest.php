<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Item;
use App\Models\User\ItemBrand;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Settings > Items (Doc 04 sections 6 and 7): a business's item categories and
 * brands - two lists that work the same way - and whether it uses brands at
 * all.
 */
class ItemLabelsApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);
    }

    private function itemOf(User $user, array $overrides = []): Item
    {
        return $this->businessOf($user)->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Office Chair', 'unit' => 'Piece', ...$overrides]);
    }

    /* ---------------------------------------------------------- access */

    public function test_every_endpoint_needs_a_token_and_a_business(): void
    {
        $brand = $this->businessOf(User::factory()->create())->itemBrands()->create(['name' => 'Ergo']);

        foreach (['categories', 'brands'] as $list) {
            $this->getJson("/api/v1/item-{$list}")->assertUnauthorized();
            $this->postJson("/api/v1/item-{$list}", ['name' => 'New'])->assertUnauthorized();
            $this->putJson("/api/v1/item-{$list}/{$brand->id}", ['name' => 'Hijacked'])->assertUnauthorized();
            $this->deleteJson("/api/v1/item-{$list}/{$brand->id}")->assertUnauthorized();
        }

        $this->getJson('/api/v1/business-settings/items')->assertUnauthorized();
        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => true])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/item-brands')->assertNotFound()->assertJsonPath('message', 'Create a workspace first.');
        $this->getJson('/api/v1/business-settings/items')->assertNotFound();

        $this->assertSame('Ergo', $brand->fresh()->name);
    }

    public function test_only_the_two_lists_exist(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/item-colours')->assertNotFound();
        $this->postJson('/api/v1/item-colours', ['name' => 'Red'])->assertNotFound();
    }

    /* ---------------------------------------------------------- the lists */

    public function test_a_category_is_added_listed_renamed_and_deleted(): void
    {
        $user = $this->signIn();

        $added = $this->postJson('/api/v1/item-categories', ['name' => 'Chairs'])
            ->assertCreated()
            ->assertJsonPath('message', 'Category added successfully.')
            ->json('data');

        $this->assertSame(['name' => 'Chairs', 'items_count' => 0], array_diff_key($added, ['id' => true]));

        $id    = $added['id'];
        $other = $this->postJson('/api/v1/item-categories', ['name' => 'Accessories'])->assertCreated()->json('data.id');
        $this->itemOf($user, ['category_id' => $id]);

        $this->getJson('/api/v1/item-categories')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $other, 'name' => 'Accessories', 'items_count' => 0],
                ['id' => $id, 'name' => 'Chairs', 'items_count' => 1],
            ]]);

        $this->putJson("/api/v1/item-categories/{$id}", ['name' => 'Seating'])
            ->assertOk()
            ->assertJsonPath('message', 'Category updated successfully.')
            ->assertJsonPath('data.name', 'Seating')
            ->assertJsonPath('data.items_count', 1);

        // The new name is what its items show.
        $this->getJson('/api/v1/items')->assertJsonPath('data.0.category', ['id' => $id, 'name' => 'Seating']);

        $this->deleteJson("/api/v1/item-categories/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Category deleted successfully.');

        $this->assertSame(['Accessories'], array_column($this->getJson('/api/v1/item-categories')->json('data'), 'name'));
    }

    public function test_a_name_is_used_once_per_list_and_business_whatever_its_case(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $id     = $this->postJson('/api/v1/item-categories', ['name' => 'Chairs'])->json('data.id');

        $this->postJson('/api/v1/item-categories', ['name' => 'chairs'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'This business already has the category "chairs".']);

        // The other list, another business, and the category's own name are fine.
        $this->postJson('/api/v1/item-brands', ['name' => 'Chairs'])->assertCreated();
        $this->postJson('/api/v1/item-categories', ['name' => 'Chairs'], $this->pick($second))->assertCreated();
        $this->putJson("/api/v1/item-categories/{$id}", ['name' => 'CHAIRS'])->assertOk()->assertJsonPath('data.name', 'CHAIRS');

        $this->postJson('/api/v1/item-brands', ['name' => 'CHAIRS'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'This business already has the brand "CHAIRS".']);

        $this->postJson('/api/v1/item-brands', ['name' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Enter a name.']);

        $this->postJson('/api/v1/item-brands', ['name' => str_repeat('b', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_deleting_one_keeps_its_items_without_it(): void
    {
        $user     = $this->signIn();
        $business = $this->businessOf($user);
        $category = $business->itemCategories()->create(['name' => 'Chairs']);
        $brand    = $business->itemBrands()->create(['name' => 'Ergo']);
        $item     = $this->itemOf($user, ['category_id' => $category->id, 'brand_id' => $brand->id]);

        $this->deleteJson("/api/v1/item-categories/{$category->id}")->assertOk();
        $this->deleteJson("/api/v1/item-brands/{$brand->id}")->assertOk();

        $this->assertSame([null, null], [$item->fresh()->category_id, $item->fresh()->brand_id]);
    }

    public function test_another_business_category_or_brand_is_not_found(): void
    {
        $user    = $this->signIn();
        $sibling = $user->workspaces()->create(['name' => 'Second Business'])->itemCategories()->create(['name' => 'Theirs']);
        $foreign = $this->businessOf(User::factory()->create())->itemBrands()->create(['name' => 'Someone Else']);
        $this->businessOf($user)->itemCategories()->create(['name' => 'Mine']);

        $this->assertSame(['Mine'], array_column($this->getJson('/api/v1/item-categories')->json('data'), 'name'));
        $this->assertSame([], $this->getJson('/api/v1/item-brands')->json('data'));

        $this->putJson("/api/v1/item-categories/{$sibling->id}", ['name' => 'Hijacked'])->assertNotFound()->assertJsonPath('message', 'Category not found.');
        $this->deleteJson("/api/v1/item-categories/{$sibling->id}")->assertNotFound();
        $this->putJson("/api/v1/item-brands/{$foreign->id}", ['name' => 'Hijacked'])->assertNotFound()->assertJsonPath('message', 'Brand not found.');
        $this->deleteJson("/api/v1/item-brands/{$foreign->id}")->assertNotFound();

        // A category's id is not a brand's.
        $this->deleteJson("/api/v1/item-brands/{$sibling->id}")->assertNotFound();

        $this->assertSame(['Theirs', 'Someone Else'], [$sibling->fresh()->name, $foreign->fresh()->name]);
    }

    public function test_deleting_a_business_deletes_its_lists(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $second->itemBrands()->create(['name' => 'Ergo']);
        $this->businessOf($user)->itemBrands()->create(['name' => 'Ergo']);

        $second->delete();

        $this->assertSame(1, ItemBrand::count());
    }

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = $this->signIn();

        $id = $this->postJson('/api/v1/item-categories', ['name' => 'Chairs'])->json('data.id');
        $this->itemOf($user, ['category_id' => $id]);
        $this->putJson("/api/v1/item-categories/{$id}", ['name' => 'Seating'])->assertOk();
        $this->deleteJson("/api/v1/item-categories/{$id}")->assertOk();
        $this->postJson('/api/v1/item-brands', ['name' => 'Ergo'])->assertCreated();

        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertSame([
            'Added item category "Chairs"',
            'Updated item category "Seating"',
            'Deleted item category "Seating"',
            'Added item brand "Ergo"',
        ], $logs->pluck('description')->all());
        $this->assertSame(['name'], $logs[1]->properties['fields']);
        $this->assertSame(1, $logs[2]->properties['items']);
    }

    /* ---------------------------------------------------------- Brand Management */

    public function test_brand_management_is_off_until_it_is_switched_on(): void
    {
        $user     = $this->signIn();
        $business = $this->businessOf($user);

        $this->getJson('/api/v1/business-settings/items')->assertOk()->assertExactJson(['data' => ['brands_enabled' => false]]);

        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Item settings saved successfully.')
            ->assertExactJson(['message' => 'Item settings saved successfully.', 'data' => ['brands_enabled' => true]]);

        $this->assertTrue($business->fresh()->brands_enabled);

        foreach ([[], ['brands_enabled' => 'yes'], ['brands_enabled' => null]] as $body) {
            $this->putJson('/api/v1/business-settings/items', $body)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['brands_enabled' => 'Choose whether the business uses brands.']);
        }
    }

    public function test_the_sidebar_payload_tells_every_page_whether_brands_are_used(): void
    {
        $user    = $this->signIn();
        $version = $this->businessOf($user)->nav_version;

        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.brands_enabled', false);

        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => true])->assertOk();

        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.brands_enabled', true);
        $this->assertSame($version + 1, $this->businessOf($user)->fresh()->nav_version);
    }

    public function test_switching_brands_off_keeps_the_brands_and_the_items_choices(): void
    {
        $user     = $this->signIn();
        $business = $this->businessOf($user);
        $business->forceFill(['brands_enabled' => true])->save();
        $brand = $business->itemBrands()->create(['name' => 'Ergo']);
        $item  = $this->itemOf($user, ['brand_id' => $brand->id]);

        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => false])->assertOk();
        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => false])->assertOk();

        $this->assertSame($brand->id, $item->fresh()->brand_id);
        $this->assertSame(['Ergo'], array_column($this->getJson('/api/v1/item-brands')->json('data'), 'name'));

        $this->assertSame(
            [['Turned off Brand Management', ['brands_enabled']], ['Turned off Brand Management', []]],
            ActivityLog::where('user_id', $user->id)->orderBy('id')->get()->map(fn (ActivityLog $log) => [$log->description, $log->properties['fields']])->all(),
        );
    }
}
