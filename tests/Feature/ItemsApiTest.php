<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Item;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A business's products and services (Doc 04): one record per item, numbered
 * within the business, shared by every app of that business and no other.
 *
 * Combos are in ItemCombosApiTest; categories, brands and Brand Management in
 * ItemLabelsApiTest.
 */
class ItemsApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type'           => 'physical',
            'usage'          => 'both',
            'name'           => 'Office Chair',
            'sku'            => 'CHR-001',
            'unit'           => 'Piece',
            'sale_price'     => '149.99',
            'purchase_price' => '95.5',
        ], $overrides);
    }

    private function itemOf(User $user, array $overrides = []): Item
    {
        return $this->businessOf($user)->items()->create($this->payload($overrides));
    }

    private function usingBrands(Workspace $business): void
    {
        $business->forceFill(['brands_enabled' => true])->save();
    }

    /* ---------------------------------------------------------- access */

    public function test_every_endpoint_needs_a_token_and_a_business(): void
    {
        $item = $this->itemOf(User::factory()->create());

        $this->getJson('/api/v1/items')->assertUnauthorized();
        $this->getJson("/api/v1/items/{$item->id}")->assertUnauthorized();
        $this->postJson('/api/v1/items', $this->payload())->assertUnauthorized();
        $this->putJson("/api/v1/items/{$item->id}", $this->payload(['name' => 'Hijacked']))->assertUnauthorized();
        $this->postJson("/api/v1/items/{$item->id}/deactivate")->assertUnauthorized();
        $this->deleteJson("/api/v1/items/{$item->id}")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/items')
            ->assertNotFound()
            ->assertJsonPath('message', 'Create a workspace first.');

        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Office Chair', 'is_active' => true]);
    }

    /* ---------------------------------------------------------- add */

    public function test_an_item_is_added_with_its_number_and_every_field(): void
    {
        $user     = $this->signIn();
        $business = $this->businessOf($user);
        $category = $business->itemCategories()->create(['name' => 'Furniture']);
        $brand    = $business->itemBrands()->create(['name' => 'Ergo']);

        $response = $this->postJson('/api/v1/items', $this->payload([
            'barcode'              => '8901234567890',
            'category_id'          => $category->id,
            'brand_id'             => $brand->id,
            'description'          => 'Mesh back, adjustable arms.',
            'sales_description'    => 'Ergonomic office chair',
            'purchase_description' => 'Model EC-200, black',
            'warranty_available'   => true,
            'warranty_period'      => 12,
            'warranty_unit'        => 'months',
            'warranty_start_rule'  => 'invoice_date',
            'warranty_terms'       => 'Parts only.',
        ]))
            ->assertCreated()
            ->assertJsonPath('message', 'Item added successfully.')
            ->assertJsonPath('data.item_number', 'I-00001')
            ->assertJsonPath('data.type', 'physical')
            ->assertJsonPath('data.usage', 'both')
            ->assertJsonPath('data.unit', 'Piece')
            ->assertJsonPath('data.barcode', '8901234567890')
            ->assertJsonPath('data.category', ['id' => $category->id, 'name' => 'Furniture'])
            ->assertJsonPath('data.brand', ['id' => $brand->id, 'name' => 'Ergo'])
            // Strings with two decimals, never floats.
            ->assertJsonPath('data.sale_price', '149.99')
            ->assertJsonPath('data.purchase_price', '95.50')
            ->assertJsonPath('data.warranty_available', true)
            ->assertJsonPath('data.warranty_period', 12)
            ->assertJsonPath('data.components', [])
            ->assertJsonPath('data.component_total', null)
            ->assertJsonPath('data.is_active', true);

        $this->assertSame([
            'id', 'item_number', 'type', 'usage', 'name', 'sku', 'barcode', 'unit', 'category_id', 'category',
            'brand_id', 'brand', 'image', 'description', 'sale_price', 'sales_description', 'purchase_price',
            'purchase_description', 'delivery_method', 'delivery_instructions', 'warranty_available',
            'warranty_period', 'warranty_unit', 'warranty_start_rule', 'warranty_terms', 'combo_pricing',
            'combo_display', 'components', 'component_total', 'is_active',
        ], array_keys($response->json('data')));

        $this->assertDatabaseHas('items', ['id' => $response->json('data.id'), 'workspace_id' => $business->id]);
    }

    public function test_the_name_type_usage_and_unit_are_required(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/items', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'usage', 'name', 'unit'])
            ->assertJsonMissingValidationErrors(['sku', 'sale_price', 'components', 'combo_pricing', 'warranty_period']);

        $this->postJson('/api/v1/items', ['type' => 'service', 'usage' => 'sales', 'name' => 'Delivery', 'unit' => 'Service'])
            ->assertCreated()
            ->assertJsonPath('data.sale_price', null)
            ->assertJsonPath('data.sku', null)
            ->assertJsonPath('data.category', null);

        $this->postJson('/api/v1/items', $this->payload(['type' => 'product', 'usage' => 'rent', 'unit' => str_repeat('u', 21)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type'  => 'Choose physical product, digital product, service or combo.',
                'usage' => 'Choose sales, purchase or both.',
                'unit',
            ]);
    }

    public function test_numbers_count_up_within_each_business_and_are_never_reused(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);

        $first = $this->postJson('/api/v1/items', $this->payload(['name' => 'One', 'sku' => null]))->json('data');
        $this->postJson('/api/v1/items', $this->payload(['name' => 'Two', 'sku' => null]))->assertJsonPath('data.item_number', 'I-00002');
        $this->postJson('/api/v1/items', $this->payload(['name' => 'Elsewhere']), $this->pick($second))->assertJsonPath('data.item_number', 'I-00001');

        $this->deleteJson("/api/v1/items/{$first['id']}")->assertOk();

        // A number is not the caller's to choose, and a deleted one is not given again.
        $this->postJson('/api/v1/items', $this->payload(['name' => 'Three', 'sku' => null, 'item_number' => 'I-00001']))
            ->assertJsonPath('data.item_number', 'I-00003');
    }

    public function test_it_belongs_to_the_business_of_the_request_whatever_the_body_says(): void
    {
        $user   = $this->signIn();
        $theirs = $this->businessOf(User::factory()->create());

        $this->postJson('/api/v1/items', $this->payload(['workspace_id' => $theirs->id]))->assertCreated();

        $this->assertSame(1, $this->businessOf($user)->items()->count());
        $this->assertSame(0, $theirs->items()->count());
    }

    public function test_prices_are_never_negative_and_at_most_two_decimals(): void
    {
        $this->signIn();

        foreach (['-1', '1.999', 'free', '99999999999999'] as $price) {
            $this->postJson('/api/v1/items', $this->payload(['sale_price' => $price, 'purchase_price' => $price]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['sale_price', 'purchase_price']);
        }

        $this->postJson('/api/v1/items', $this->payload(['sale_price' => 0, 'purchase_price' => '9999999999999.99']))
            ->assertCreated()
            ->assertJsonPath('data.sale_price', '0.00')
            ->assertJsonPath('data.purchase_price', '9999999999999.99');
    }

    public function test_a_sku_and_a_barcode_are_unique_within_the_business_only(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $chair  = $this->itemOf($user, ['barcode' => '890123']);

        foreach (['sku' => 'CHR-001', 'barcode' => '890123'] as $field => $taken) {
            $label = $field === 'sku' ? 'SKU' : 'barcode';

            $this->postJson('/api/v1/items', $this->payload(['name' => 'Another Chair', 'sku' => null, $field => $taken]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field => "Another item of this business already has that {$label}."]);

            // Any number may go without one.
            $this->postJson('/api/v1/items', $this->payload(['name' => "No {$label} 1", 'sku' => null, 'barcode' => null]))->assertCreated();
            $this->postJson('/api/v1/items', $this->payload(['name' => "No {$label} 2", 'sku' => '', 'barcode' => '']))->assertCreated();
        }

        // Another business - the owner's own - may use them.
        $this->postJson('/api/v1/items', $this->payload(['barcode' => '890123']), $this->pick($second))->assertCreated();

        // An item keeps its own on edit.
        $this->putJson("/api/v1/items/{$chair->id}", $this->payload(['name' => 'Office Chair v2', 'barcode' => '890123']))->assertOk();
    }

    public function test_a_category_and_a_brand_are_the_business_own(): void
    {
        $user     = $this->signIn();
        $second   = $user->workspaces()->create(['name' => 'Second Business']);
        $category = $second->itemCategories()->create(['name' => 'Furniture']);
        $brand    = $this->businessOf(User::factory()->create())->itemBrands()->create(['name' => 'Ergo']);

        $this->postJson('/api/v1/items', $this->payload(['category_id' => $category->id, 'brand_id' => $brand->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id' => "Choose one of this business's categories.",
                'brand_id'    => "Choose one of this business's brands.",
            ]);
    }

    public function test_a_digital_product_keeps_how_it_is_delivered(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/items', $this->payload([
            'type'                  => 'digital',
            'unit'                  => 'Licence',
            'delivery_method'       => 'license_key',
            'delivery_instructions' => 'Sent by e-mail within a day.',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.delivery_method', 'license_key')
            ->assertJsonPath('data.delivery_instructions', 'Sent by e-mail within a day.');

        $this->postJson('/api/v1/items', $this->payload(['type' => 'digital', 'sku' => null, 'delivery_method' => 'pigeon']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_method');
    }

    public function test_a_warranty_needs_its_period_unit_and_start_rule(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/items', $this->payload(['warranty_available' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['warranty_period', 'warranty_unit', 'warranty_start_rule']);

        $this->postJson('/api/v1/items', $this->payload(['warranty_available' => true, 'warranty_period' => 0, 'warranty_unit' => 'weeks', 'warranty_start_rule' => 'whenever']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['warranty_period', 'warranty_unit', 'warranty_start_rule']);

        $this->postJson('/api/v1/items', $this->payload(['warranty_available' => false]))
            ->assertCreated()
            ->assertJsonPath('data.warranty_available', false)
            ->assertJsonPath('data.warranty_period', null);
    }

    /* ---------------------------------------------------------- list */

    public function test_a_new_business_has_no_items(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/items')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_the_list_is_alphabetical_and_paginated(): void
    {
        $user = $this->signIn();

        foreach (['Office Chair', 'Desk', 'Assembly'] as $name) {
            $this->itemOf($user, ['name' => $name, 'sku' => null]);
        }

        $this->assertSame(['Assembly', 'Desk', 'Office Chair'], array_column($this->getJson('/api/v1/items')->assertOk()->json('data'), 'name'));

        $this->getJson('/api/v1/items?per_page=10')
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.has_next_page', false);
    }

    public function test_the_list_is_searched_by_name_number_sku_barcode_category_and_brand(): void
    {
        $user      = $this->signIn();
        $business  = $this->businessOf($user);
        $furniture = $business->itemCategories()->create(['name' => 'Furniture']);
        $ergo      = $business->itemBrands()->create(['name' => 'Ergo']);

        $this->itemOf($user, ['name' => 'Office Chair', 'sku' => 'CHR-001', 'brand_id' => $ergo->id]);
        $this->itemOf($user, ['name' => 'Desk', 'sku' => 'DSK-001', 'barcode' => '5012345', 'category_id' => $furniture->id]);
        $this->itemOf($user, ['name' => 'Assembly', 'sku' => null, 'type' => 'service', 'unit' => 'Hour']);

        $names = fn (string $search) => array_column($this->getJson('/api/v1/items?search='.urlencode($search))->assertOk()->json('data'), 'name');

        $this->assertSame(['Office Chair'], $names('chair'));       // name
        $this->assertSame(['Assembly'], $names('I-00003'));         // number
        $this->assertSame(['Desk'], $names('dsk'));                 // SKU
        $this->assertSame(['Desk'], $names('50123'));               // barcode
        $this->assertSame(['Desk'], $names('furni'));               // category

        // The brand only while the business uses brands.
        $this->assertSame([], $names('ergo'));
        $this->usingBrands($business);
        $this->assertSame(['Office Chair'], $names('ergo'));
    }

    public function test_the_list_is_filtered_by_type_usage_category_brand_and_status(): void
    {
        $user      = $this->signIn();
        $business  = $this->businessOf($user);
        $furniture = $business->itemCategories()->create(['name' => 'Furniture']);
        $ergo      = $business->itemBrands()->create(['name' => 'Ergo']);

        $this->itemOf($user, ['name' => 'Office Chair', 'sku' => null, 'category_id' => $furniture->id, 'brand_id' => $ergo->id]);
        $this->itemOf($user, ['name' => 'Desk', 'sku' => null, 'usage' => 'sales', 'category_id' => $furniture->id]);
        $this->itemOf($user, ['name' => 'Assembly', 'sku' => null, 'type' => 'service', 'usage' => 'sales', 'unit' => 'Hour']);
        $this->itemOf($user, ['name' => 'Ink', 'sku' => null, 'usage' => 'purchase', 'is_active' => false]);

        $names = fn (string $query) => array_column($this->getJson("/api/v1/items?{$query}")->assertOk()->json('data'), 'name');

        $this->assertSame(['Assembly'], $names('type=service'));
        $this->assertSame(['Desk', 'Ink', 'Office Chair'], $names('type=physical'));
        // Exactly: "sales" leaves out the items used for both.
        $this->assertSame(['Assembly', 'Desk'], $names('usage=sales'));
        $this->assertSame(['Office Chair'], $names('usage=both'));
        $this->assertSame(['Desk', 'Office Chair'], $names("category_id={$furniture->id}"));
        $this->assertSame(['Ink'], $names('status=inactive'));
        $this->assertSame(['Assembly', 'Desk', 'Office Chair'], $names('status=active'));

        // The brand only while the business uses brands.
        $this->assertSame(['Assembly', 'Desk', 'Ink', 'Office Chair'], $names("brand_id={$ergo->id}"));
        $this->usingBrands($business);
        $this->assertSame(['Office Chair'], $names("brand_id={$ergo->id}"));

        $this->getJson('/api/v1/items?type=bundle&usage=rent&status=gone')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'usage', 'status']);
    }

    /* ---------------------------------------------------------- show, edit, status, delete */

    public function test_one_item_is_shown_whole(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user, ['sales_description' => 'Ergonomic office chair']);

        $this->getJson("/api/v1/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.item_number', 'I-00001')
            ->assertJsonPath('data.sales_description', 'Ergonomic office chair')
            ->assertJsonPath('data.purchase_price', '95.50');
    }

    public function test_an_item_is_updated(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user);

        $this->putJson("/api/v1/items/{$item->id}", $this->payload(['sale_price' => '160', 'unit' => 'Box', 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('message', 'Item updated successfully.')
            ->assertJsonPath('data.sale_price', '160.00')
            ->assertJsonPath('data.unit', 'Box')
            ->assertJsonPath('data.item_number', 'I-00001')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_a_field_left_out_is_kept_so_hidden_details_survive(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user, [
            'purchase_description' => 'Model EC-200',
            'warranty_available'   => true,
            'warranty_period'      => 2,
            'warranty_unit'        => 'years',
            'warranty_start_rule'  => 'invoice_date',
        ]);

        // Only sold now, and a service: the form hides the purchase side and
        // the warranty, and sends neither.
        $this->putJson("/api/v1/items/{$item->id}", ['type' => 'service', 'usage' => 'sales', 'name' => 'Chair Repair', 'unit' => 'Service'])
            ->assertOk()
            ->assertJsonPath('data.purchase_price', '95.50')
            ->assertJsonPath('data.purchase_description', 'Model EC-200')
            ->assertJsonPath('data.warranty_available', true)
            ->assertJsonPath('data.warranty_period', 2);
    }

    public function test_an_item_is_activated_and_deactivated(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user);

        $this->postJson("/api/v1/items/{$item->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('message', 'Item deactivated.')
            ->assertJsonPath('data.is_active', false);

        $this->postJson("/api/v1/items/{$item->id}/activate")
            ->assertOk()
            ->assertJsonPath('message', 'Item activated.')
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(
            ['Deactivated item "Office Chair"', 'Activated item "Office Chair"'],
            ActivityLog::where('user_id', $user->id)->orderBy('id')->pluck('description')->all(),
        );
    }

    public function test_an_item_is_deleted(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user);

        $this->deleteJson("/api/v1/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Item deleted successfully.');

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_another_business_item_is_not_found(): void
    {
        $user    = $this->signIn();
        $second  = $user->workspaces()->create(['name' => 'Second Business']);
        $sibling = $second->items()->create($this->payload(['name' => 'In Second']));
        $foreign = $this->itemOf(User::factory()->create(), ['name' => 'Someone Else']);
        $this->itemOf($user);

        $this->assertSame(['Office Chair'], array_column($this->getJson('/api/v1/items')->json('data'), 'name'));

        foreach ([$sibling, $foreign] as $item) {
            $this->getJson("/api/v1/items/{$item->id}")->assertNotFound()->assertJsonPath('message', 'Item not found.');
            $this->putJson("/api/v1/items/{$item->id}", $this->payload(['name' => 'Hijacked', 'sku' => 'NEW-1']))->assertNotFound();
            $this->postJson("/api/v1/items/{$item->id}/deactivate")->assertNotFound();
            $this->deleteJson("/api/v1/items/{$item->id}")->assertNotFound();
        }

        $this->assertSame(['In Second', 'Someone Else'], [$sibling->fresh()->name, $foreign->fresh()->name]);
        $this->assertTrue($sibling->fresh()->is_active && $foreign->fresh()->is_active);
    }

    public function test_deleting_a_business_deletes_its_items_and_their_counter(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $second->items()->create($this->payload());
        $this->itemOf($user);

        $second->delete();

        $this->assertSame(1, Item::count());
        $this->assertDatabaseMissing('workspace_sequences', ['workspace_id' => $second->id]);
    }

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = $this->signIn();

        $id = $this->postJson('/api/v1/items', $this->payload())->json('data.id');
        $this->putJson("/api/v1/items/{$id}", $this->payload(['sale_price' => '150']))->assertOk();
        $this->deleteJson("/api/v1/items/{$id}")->assertOk();

        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertSame(
            ['Added item "Office Chair"', 'Updated item "Office Chair"', 'Deleted item "Office Chair"'],
            $logs->pluck('description')->all(),
        );
        $this->assertSame(['sale_price'], $logs[1]->properties['fields']);
    }
}
