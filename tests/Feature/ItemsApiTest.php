<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Item;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A business's products and services (Doc 01 section 10): one record per item,
 * shared by every app of that business and no other.
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
            'type'           => 'product',
            'name'           => 'Office Chair',
            'sku'            => 'CHR-001',
            'unit'           => 'pcs',
            'sale_price'     => '149.99',
            'purchase_price' => '95.5',
        ], $overrides);
    }

    private function itemOf(User $user, array $overrides = []): Item
    {
        return $this->businessOf($user)->items()->create($this->payload($overrides));
    }

    /* ---------------------------------------------------------- access */

    public function test_every_endpoint_needs_a_token_and_a_business(): void
    {
        $item = $this->itemOf(User::factory()->create());

        $this->getJson('/api/v1/items')->assertUnauthorized();
        $this->postJson('/api/v1/items', $this->payload())->assertUnauthorized();
        $this->putJson("/api/v1/items/{$item->id}", $this->payload(['name' => 'Hijacked']))->assertUnauthorized();
        $this->deleteJson("/api/v1/items/{$item->id}")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/items')
            ->assertNotFound()
            ->assertJsonPath('message', 'Create a workspace first.');

        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Office Chair']);
    }

    /* ---------------------------------------------------------- add */

    public function test_an_item_is_added_with_its_prices_to_the_cent(): void
    {
        $user = $this->signIn();

        $response = $this->postJson('/api/v1/items', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Item added successfully.')
            ->assertJsonPath('data.type', 'product')
            ->assertJsonPath('data.sku', 'CHR-001')
            // Strings with two decimals, never floats.
            ->assertJsonPath('data.sale_price', '149.99')
            ->assertJsonPath('data.purchase_price', '95.50')
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(
            ['id', 'type', 'name', 'sku', 'unit', 'sale_price', 'purchase_price', 'description', 'is_active'],
            array_keys($response->json('data')),
        );

        $this->assertDatabaseHas('items', ['id' => $response->json('data.id'), 'workspace_id' => $this->businessOf($user)->id]);
    }

    public function test_only_the_type_and_name_are_required(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/items', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'name'])
            ->assertJsonMissingValidationErrors(['sku', 'sale_price']);

        $this->postJson('/api/v1/items', ['type' => 'service', 'name' => 'Delivery'])
            ->assertCreated()
            ->assertJsonPath('data.sale_price', null)
            ->assertJsonPath('data.sku', null);

        $this->postJson('/api/v1/items', $this->payload(['type' => 'bundle']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'Choose product or service.']);
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

    public function test_a_sku_is_unique_within_the_business_only(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $chair  = $this->itemOf($user);

        $this->postJson('/api/v1/items', $this->payload(['name' => 'Another Chair']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku' => 'Another item of this business already has that SKU.']);

        // Another business - the owner's own - may use it.
        $this->postJson('/api/v1/items', $this->payload(), $this->pick($second))->assertCreated();

        // An item keeps its own on edit.
        $this->putJson("/api/v1/items/{$chair->id}", $this->payload(['name' => 'Office Chair v2']))->assertOk();

        // Any number may go without one.
        $this->postJson('/api/v1/items', $this->payload(['name' => 'No SKU 1', 'sku' => null]))->assertCreated();
        $this->postJson('/api/v1/items', $this->payload(['name' => 'No SKU 2', 'sku' => '']))->assertCreated();
    }

    /* ---------------------------------------------------------- list */

    public function test_the_list_is_alphabetical_paginated_searched_and_filtered(): void
    {
        $user = $this->signIn();

        $this->itemOf($user, ['name' => 'Office Chair', 'sku' => 'CHR-001']);
        $this->itemOf($user, ['name' => 'Desk', 'sku' => 'DSK-001']);
        $this->itemOf($user, ['name' => 'Assembly', 'sku' => null, 'type' => 'service']);
        $this->itemOf($user, ['name' => 'Old Lamp', 'sku' => 'LMP-001', 'is_active' => false]);

        $names = fn (string $query = '') => array_column($this->getJson("/api/v1/items?{$query}")->assertOk()->json('data'), 'name');

        $this->assertSame(['Assembly', 'Desk', 'Office Chair', 'Old Lamp'], $names());
        $this->assertSame(['Desk'], $names('search=dsk'));                 // SKU
        $this->assertSame(['Office Chair'], $names('search=chair'));       // name
        $this->assertSame(['Assembly'], $names('type=service'));
        $this->assertSame(['Desk', 'Office Chair'], $names('type=product&status=active'));
        $this->assertSame(['Old Lamp'], $names('status=inactive'));

        $this->getJson('/api/v1/items?per_page=10')
            ->assertJsonPath('pagination.total', 4)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.has_next_page', false);

        $this->getJson('/api/v1/items?type=bundle')->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    /* ---------------------------------------------------------- edit and delete */

    public function test_an_item_is_updated_and_deleted(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user);

        $this->putJson("/api/v1/items/{$item->id}", $this->payload(['sale_price' => '160', 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('message', 'Item updated successfully.')
            ->assertJsonPath('data.sale_price', '160.00')
            ->assertJsonPath('data.is_active', false);

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
            $this->putJson("/api/v1/items/{$item->id}", $this->payload(['name' => 'Hijacked', 'sku' => 'NEW-1']))
                ->assertNotFound()
                ->assertJsonPath('message', 'Item not found.');
            $this->deleteJson("/api/v1/items/{$item->id}")->assertNotFound();
        }

        $this->assertSame(['In Second', 'Someone Else'], [$sibling->fresh()->name, $foreign->fresh()->name]);

        $second->delete();
        $this->assertDatabaseMissing('items', ['id' => $sibling->id]);
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
