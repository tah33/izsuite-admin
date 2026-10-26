<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Item;
use App\Models\User\ItemComponent;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Combos (Doc 04 section 14): two or more of the business's items sold as one
 * package - never containing itself or another combo, so no circle can form.
 */
class ItemCombosApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Item $chair;

    private Item $lamp;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        $this->user  = $this->signIn();
        $this->chair = $this->itemOf(['name' => 'Office Chair', 'sale_price' => '149.99']);
        $this->lamp  = $this->itemOf(['name' => 'Desk Lamp', 'sale_price' => '10.01']);
    }

    private function itemOf(array $overrides = []): Item
    {
        return $this->businessOf($this->user)->items()->create([
            'type'       => 'physical',
            'usage'      => 'both',
            'name'       => 'Item',
            'unit'       => 'Piece',
            'sale_price' => '1.00',
            ...$overrides,
        ]);
    }

    private function combo(array $overrides = [], ?array $components = null): array
    {
        return array_merge([
            'type'          => 'combo',
            'usage'         => 'sales',
            'name'          => 'Office Set',
            'unit'          => 'Set',
            'combo_pricing' => 'fixed',
            'combo_display' => 'combo_only',
            'sale_price'    => '199.00',
            'components'    => $components ?? [
                ['item_id' => $this->chair->id, 'quantity' => 2],
                ['item_id' => $this->lamp->id, 'quantity' => '1.5'],
            ],
        ], $overrides);
    }

    private function comboId(array $overrides = []): int
    {
        return $this->postJson('/api/v1/items', $this->combo($overrides))->assertCreated()->json('data.id');
    }

    /* ---------------------------------------------------------- making one */

    public function test_a_combo_is_made_of_two_or_more_items_with_their_quantities(): void
    {
        $response = $this->postJson('/api/v1/items', $this->combo(['combo_pricing' => 'sum', 'combo_display' => 'combo_and_components', 'sale_price' => null]))
            ->assertCreated()
            ->assertJsonPath('data.type', 'combo')
            ->assertJsonPath('data.item_number', 'I-00003')
            ->assertJsonPath('data.combo_pricing', 'sum')
            ->assertJsonPath('data.combo_display', 'combo_and_components')
            ->assertJsonPath('data.components', [
                ['item_id' => $this->chair->id, 'item_number' => 'I-00001', 'name' => 'Office Chair', 'unit' => 'Piece', 'quantity' => '2.000', 'sale_price' => '149.99', 'is_active' => true],
                ['item_id' => $this->lamp->id, 'item_number' => 'I-00002', 'name' => 'Desk Lamp', 'unit' => 'Piece', 'quantity' => '1.500', 'sale_price' => '10.01', 'is_active' => true],
            ])
            // 2 × 149.99 + 1.5 × 10.01 = 314.995, rounded half up once, at the end.
            ->assertJsonPath('data.component_total', '315.00');

        $this->assertSame([0, 1], ItemComponent::where('combo_id', $response->json('data.id'))->orderBy('id')->pluck('sort_order')->all());

        // Shown whole too.
        $this->getJson('/api/v1/items/'.$response->json('data.id'))
            ->assertJsonPath('data.components.1.name', 'Desk Lamp')
            ->assertJsonPath('data.component_total', '315.00');
    }

    public function test_a_combo_needs_its_methods_and_at_least_two_components(): void
    {
        $this->postJson('/api/v1/items', ['type' => 'combo', 'usage' => 'sales', 'name' => 'Office Set', 'unit' => 'Set'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['combo_pricing', 'combo_display', 'components' => 'A combo needs at least two components.']);

        $this->postJson('/api/v1/items', $this->combo([], [['item_id' => $this->chair->id, 'quantity' => 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['components' => 'A combo needs at least two components.']);

        $this->postJson('/api/v1/items', $this->combo(['usage' => 'both', 'combo_pricing' => 'average', 'combo_display' => 'everything']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usage' => 'A combo can only be sold.', 'combo_pricing', 'combo_display']);

        // Priced on its own, it needs a price; priced as its components' sum, it does not.
        $this->postJson('/api/v1/items', $this->combo(['sale_price' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sale_price' => 'A combo with its own price needs a sales price.']);

        $this->postJson('/api/v1/items', $this->combo(['sale_price' => null, 'combo_pricing' => 'sum']))->assertCreated();
    }

    public function test_each_component_is_a_quantity_above_zero_of_an_item_listed_once(): void
    {
        $this->postJson('/api/v1/items', $this->combo([], [
            ['item_id' => $this->chair->id, 'quantity' => 0],
            ['item_id' => $this->lamp->id, 'quantity' => '1.2345'],
            ['item_id' => $this->lamp->id],
            ['quantity' => 1],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'components.0.quantity' => 'The quantity must be more than zero.',
                'components.1.quantity' => 'The quantity has too many decimal places.',
                'components.2.quantity',
                'components.1.item_id' => 'Each item can be a component once.',
                'components.3.item_id',
            ]);
    }

    public function test_a_component_is_an_active_sold_item_of_this_business_and_never_a_combo(): void
    {
        $theirs   = $this->businessOf(User::factory()->create())->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Theirs', 'unit' => 'Piece']);
        $inactive = $this->itemOf(['name' => 'Old Lamp', 'is_active' => false]);
        $bought   = $this->itemOf(['name' => 'Printer Ink', 'usage' => 'purchase']);
        $combo    = Item::find($this->comboId());

        $this->postJson('/api/v1/items', $this->combo(['name' => 'Big Set'], [
            ['item_id' => $theirs->id, 'quantity' => 1],
            ['item_id' => $combo->id, 'quantity' => 1],
            ['item_id' => $inactive->id, 'quantity' => 1],
            ['item_id' => $bought->id, 'quantity' => 1],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'components.0.item_id' => 'Choose an item of this business.',
                'components.1.item_id' => 'A combo cannot contain another combo.',
                'components.2.item_id' => 'Old Lamp is inactive.',
                'components.3.item_id' => 'Printer Ink is only purchased, so it cannot be sold in a combo.',
            ]);

        $this->putJson("/api/v1/items/{$combo->id}", $this->combo([], [
            ['item_id' => $combo->id, 'quantity' => 1],
            ['item_id' => $this->chair->id, 'quantity' => 1],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['components.0.item_id' => 'A combo cannot contain itself.']);
    }

    public function test_a_component_deactivated_later_may_stay_but_none_is_added_inactive(): void
    {
        $id   = $this->comboId();
        $desk = $this->itemOf(['name' => 'Desk', 'is_active' => false]);

        $this->postJson("/api/v1/items/{$this->lamp->id}/deactivate")->assertOk();

        // The rest of the combo can still be edited.
        $this->putJson("/api/v1/items/{$id}", $this->combo(['name' => 'Office Set 2025']))
            ->assertOk()
            ->assertJsonPath('data.components.1.is_active', false);

        $this->putJson("/api/v1/items/{$id}", $this->combo([], [
            ['item_id' => $desk->id, 'quantity' => 1],
            ['item_id' => $this->lamp->id, 'quantity' => 1],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['components.0.item_id' => 'Desk is inactive.'])
            ->assertJsonMissingValidationErrors('components.1.item_id');
    }

    /* ---------------------------------------------------------- changing one */

    public function test_editing_the_components_replaces_them_in_the_order_given(): void
    {
        $id = $this->comboId();

        $this->putJson("/api/v1/items/{$id}", $this->combo([], [
            ['item_id' => $this->lamp->id, 'quantity' => 3],
            ['item_id' => $this->chair->id, 'quantity' => 1],
        ]))
            ->assertOk()
            ->assertJsonPath('data.components.0.name', 'Desk Lamp')
            ->assertJsonPath('data.components.0.quantity', '3.000')
            ->assertJsonPath('data.components.1.name', 'Office Chair');

        $this->putJson("/api/v1/items/{$id}", $this->combo(['name' => 'Office Set Plus'], [
            ['item_id' => $this->lamp->id, 'quantity' => '3.000'],
            ['item_id' => $this->chair->id, 'quantity' => 1],
        ]))->assertOk();

        $this->assertSame(2, ItemComponent::where('combo_id', $id)->count());

        $fields = ActivityLog::where('user_id', $this->user->id)->where('description', 'like', 'Updated item%')->orderBy('id')->pluck('properties')->pluck('fields')->all();
        $this->assertSame([['components'], ['name']], $fields);
    }

    public function test_a_combo_that_becomes_another_type_gives_up_its_components(): void
    {
        $id = $this->comboId();

        $this->putJson("/api/v1/items/{$id}", ['type' => 'physical', 'usage' => 'both', 'name' => 'Office Set', 'unit' => 'Set', 'components' => null])
            ->assertOk()
            ->assertJsonPath('data.components', [])
            ->assertJsonPath('data.combo_pricing', null)
            ->assertJsonPath('data.combo_display', null)
            ->assertJsonPath('data.component_total', null);

        $this->assertSame(0, ItemComponent::count());
        $this->deleteJson("/api/v1/items/{$this->chair->id}")->assertOk();
    }

    public function test_an_item_in_a_combo_cannot_become_a_combo_or_stop_being_sold(): void
    {
        $this->comboId();

        $this->putJson("/api/v1/items/{$this->chair->id}", $this->combo(['name' => 'Office Chair'], [
            ['item_id' => $this->lamp->id, 'quantity' => 1],
            ['item_id' => $this->itemOf(['name' => 'Cushion'])->id, 'quantity' => 1],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'This item is part of the combo "Office Set", so it cannot be a combo itself.']);

        $this->putJson("/api/v1/items/{$this->chair->id}", ['type' => 'physical', 'usage' => 'purchase', 'name' => 'Office Chair', 'unit' => 'Piece'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usage' => 'This item is part of the combo "Office Set", so it must stay sold.']);

        $this->putJson("/api/v1/items/{$this->chair->id}", ['type' => 'physical', 'usage' => 'sales', 'name' => 'Office Chair', 'unit' => 'Piece'])->assertOk();
    }

    public function test_another_business_item_says_nothing_of_its_combos(): void
    {
        $other   = User::factory()->create();
        $theirs  = $this->businessOf($other)->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Their Chair', 'unit' => 'Piece']);
        $partner = $this->businessOf($other)->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Their Lamp', 'unit' => 'Piece']);
        $combo   = $this->businessOf($other)->items()->create(['type' => 'combo', 'usage' => 'sales', 'name' => 'Secret Set', 'unit' => 'Set', 'combo_pricing' => 'sum', 'combo_display' => 'combo_only']);
        ItemComponent::create(['combo_id' => $combo->id, 'component_id' => $theirs->id, 'quantity' => 1]);
        ItemComponent::create(['combo_id' => $combo->id, 'component_id' => $partner->id, 'quantity' => 1]);

        $this->putJson("/api/v1/items/{$theirs->id}", $this->combo())
            ->assertNotFound()
            ->assertJsonPath('message', 'Item not found.');

        $this->deleteJson("/api/v1/items/{$theirs->id}")->assertNotFound();
        $this->assertSame('physical', $theirs->fresh()->type);
    }

    /* ---------------------------------------------------------- status and delete */

    public function test_an_item_in_a_combo_is_not_deleted_but_can_be_deactivated(): void
    {
        $id = $this->comboId();

        $this->deleteJson("/api/v1/items/{$this->chair->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This item is part of the combo "Office Set". Take it out of the combo first, or deactivate it instead.');

        $this->assertDatabaseHas('items', ['id' => $this->chair->id]);
        $this->postJson("/api/v1/items/{$this->chair->id}/deactivate")->assertOk();

        // Deleting the combo takes its components with it, not their items.
        $this->deleteJson("/api/v1/items/{$id}")->assertOk();
        $this->assertSame(0, ItemComponent::count());
        $this->deleteJson("/api/v1/items/{$this->chair->id}")->assertOk();
        $this->assertDatabaseHas('items', ['id' => $this->lamp->id]);
    }

    public function test_deleting_the_business_deletes_its_combos_and_components(): void
    {
        $this->comboId();

        $this->businessOf($this->user)->delete();

        $this->assertSame([0, 0], [Item::count(), ItemComponent::count()]);
    }

    /* ---------------------------------------------------------- picking components */

    public function test_the_picker_offers_only_what_a_combo_can_be_made_of(): void
    {
        $this->comboId();
        $this->itemOf(['name' => 'Old Lamp', 'is_active' => false]);
        $this->itemOf(['name' => 'Printer Ink', 'usage' => 'purchase']);
        $this->itemOf(['name' => 'Fitting', 'type' => 'service', 'usage' => 'sales', 'unit' => 'Hour']);

        $this->assertSame(
            ['Desk Lamp', 'Fitting', 'Office Chair'],
            array_column($this->getJson('/api/v1/items?for_combo=1')->assertOk()->json('data'), 'name'),
        );
        $this->assertSame(
            ['Office Chair'],
            array_column($this->getJson('/api/v1/items?for_combo=1&search=chair')->json('data'), 'name'),
        );
    }
}
