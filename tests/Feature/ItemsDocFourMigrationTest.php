<?php

namespace Tests\Feature;

use App\Models\User\Item;
use App\Models\User\ItemComponent;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration that gave items Doc 04's shape: items saved before it - a
 * product with a unit or without, a service - must come through numbered and
 * with nothing lost.
 */
class ItemsDocFourMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2025_01_01_000075_reshape_items_for_doc_04.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** An item as it was saved before the migration. */
    private function legacyItem(Workspace $business, array $fields): int
    {
        return DB::table('items')->insertGetId([
            'workspace_id' => $business->id,
            'type'         => 'product',
            'name'         => 'Legacy',
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
            ...$fields,
        ]);
    }

    public function test_existing_items_are_numbered_and_keep_everything_in_their_new_shape(): void
    {
        $user   = User::factory()->create();
        $first  = $user->workspaces()->create(['name' => 'First']);
        $second = $user->workspaces()->create(['name' => 'Second']);

        $migration = require database_path(self::MIGRATION);
        $migration->down();

        $chair    = $this->legacyItem($first, ['name' => 'Office Chair', 'sku' => 'CHR-001', 'unit' => 'pcs', 'sale_price' => '149.99', 'description' => 'Mesh back']);
        $cable    = $this->legacyItem($first, ['name' => 'Cable', 'unit' => null]);
        $repair   = $this->legacyItem($first, ['name' => 'Repair', 'type' => 'service', 'unit' => '']);
        $elsewhere = $this->legacyItem($second, ['name' => 'Elsewhere', 'type' => 'service', 'unit' => 'Hour']);

        $migration->up();

        $row = fn (int $id) => DB::table('items')->find($id);

        $this->assertSame(
            ['I-00001', 'physical', 'both', 'pcs', 'CHR-001', '149.99', 'Mesh back'],
            [$row($chair)->item_number, $row($chair)->type, $row($chair)->usage, $row($chair)->unit, $row($chair)->sku, number_format((float) $row($chair)->sale_price, 2, '.', ''), $row($chair)->description],
        );

        // A missing unit becomes Piece, or Service for a service.
        $this->assertSame(['I-00002', 'physical', 'Piece'], [$row($cable)->item_number, $row($cable)->type, $row($cable)->unit]);
        $this->assertSame(['I-00003', 'service', 'Service'], [$row($repair)->item_number, $row($repair)->type, $row($repair)->unit]);

        // Numbered within its own business.
        $this->assertSame(['I-00001', 'service', 'Hour'], [$row($elsewhere)->item_number, $row($elsewhere)->type, $row($elsewhere)->unit]);

        // Each business's counter carries on from its last.
        $this->assertSame(3, DB::table('workspace_sequences')->where(['workspace_id' => $first->id, 'name' => 'item'])->value('last_value'));
        $this->assertSame('I-00004', $first->items()->create(['name' => 'Next', 'unit' => 'Piece'])->item_number);

        $this->assertTrue(Schema::hasTable('item_components'));
    }

    public function test_rolling_back_gives_items_their_old_shape(): void
    {
        $user     = User::factory()->create();
        $business = $user->workspaces()->create(['name' => 'First']);
        $chair    = $business->items()->create(['name' => 'Office Chair', 'unit' => 'Piece']);
        $lamp     = $business->items()->create(['name' => 'Desk Lamp', 'type' => 'digital', 'unit' => 'Licence']);
        $combo    = $business->items()->create(['name' => 'Office Set', 'type' => 'combo', 'usage' => 'sales', 'unit' => 'Set', 'combo_pricing' => 'sum', 'combo_display' => 'combo_only']);
        ItemComponent::create(['combo_id' => $combo->id, 'component_id' => $chair->id, 'quantity' => 1]);
        ItemComponent::create(['combo_id' => $combo->id, 'component_id' => $lamp->id, 'quantity' => 1]);
        $business->items()->create(['name' => 'Repair', 'type' => 'service', 'unit' => 'Service']);

        (require database_path(self::MIGRATION))->down();

        $this->assertSame(
            ['Office Chair' => 'product', 'Desk Lamp' => 'product', 'Office Set' => 'product', 'Repair' => 'service'],
            DB::table('items')->orderBy('id')->pluck('type', 'name')->all(),
        );
        $this->assertFalse(Schema::hasColumn('items', 'item_number'));
        $this->assertFalse(Schema::hasColumn('items', 'category_id'));
        $this->assertFalse(Schema::hasTable('item_components'));
        $this->assertSame(0, DB::table('workspace_sequences')->where('name', Item::NUMBER_SEQUENCE)->count());
    }
}
