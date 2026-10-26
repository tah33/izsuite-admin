<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Items as Doc 04 describes them:
 *
 * - item_number: I-00001, counted per business (the "item" counter of
 *   workspace_sequences). Existing items are numbered in the order they were
 *   made.
 * - type: physical, digital, service or combo - an existing product becomes a
 *   physical one.
 * - usage: sales, purchase or both - where the item can be picked. Existing
 *   items can be picked everywhere.
 * - A unit for every item: an existing one without becomes "Piece", or
 *   "Service" for a service.
 * - A barcode, unique within the business like the SKU; a category and a
 *   brand; a sales and a purchase description beside the general one.
 * - A digital product's delivery method and instructions, a physical
 *   product's warranty, and a combo's pricing and display methods - its
 *   components are rows of item_components.
 */
return new class extends Migration
{
    private const NEW_COLUMNS = [
        'item_number', 'usage', 'barcode', 'sales_description', 'purchase_description', 'delivery_method',
        'delivery_instructions', 'warranty_available', 'warranty_period', 'warranty_unit', 'warranty_start_rule',
        'warranty_terms', 'combo_pricing', 'combo_display',
    ];

    public function up(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->string('item_number', 20)->nullable()->after('workspace_id');
            $t->string('usage', 10)->default('both')->after('type');
            $t->string('barcode', 64)->nullable()->after('sku');
            $t->foreignId('category_id')->nullable()->after('barcode')->constrained('item_categories')->nullOnDelete();
            $t->foreignId('brand_id')->nullable()->after('category_id')->constrained('item_brands')->nullOnDelete();
            $t->text('sales_description')->nullable()->after('sale_price');
            $t->text('purchase_description')->nullable()->after('purchase_price');
            $t->string('delivery_method', 20)->nullable()->after('description');
            $t->text('delivery_instructions')->nullable()->after('delivery_method');
            $t->boolean('warranty_available')->default(false)->after('delivery_instructions');
            $t->unsignedSmallInteger('warranty_period')->nullable()->after('warranty_available');
            $t->string('warranty_unit', 10)->nullable()->after('warranty_period');
            $t->string('warranty_start_rule', 20)->nullable()->after('warranty_unit');
            $t->text('warranty_terms')->nullable()->after('warranty_start_rule');
            $t->string('combo_pricing', 10)->nullable()->after('warranty_terms');
            $t->string('combo_display', 25)->nullable()->after('combo_pricing');
        });

        DB::table('items')->where('type', 'product')->update(['type' => 'physical']);
        DB::table('items')->where('type', 'physical')->where(fn ($q) => $q->whereNull('unit')->orWhere('unit', ''))->update(['unit' => 'Piece']);
        DB::table('items')->where('type', 'service')->where(fn ($q) => $q->whereNull('unit')->orWhere('unit', ''))->update(['unit' => 'Service']);

        $this->numberItems();

        Schema::table('items', function (Blueprint $t) {
            $t->string('item_number', 20)->nullable(false)->change();
            $t->string('type', 10)->default('physical')->change();
            $t->unique(['workspace_id', 'item_number']);
            $t->unique(['workspace_id', 'barcode']);
        });

        // Both ends cascade: deleting a business takes its combos and their
        // components together, in whatever order. Deleting one item that is
        // part of a combo is stopped earlier, by the API, with a message.
        Schema::create('item_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('combo_id')->constrained('items')->cascadeOnDelete();
            $t->foreignId('component_id')->constrained('items')->cascadeOnDelete();
            $t->decimal('quantity', 15, 3);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();

            $t->unique(['combo_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_components');

        DB::table('items')->whereIn('type', ['physical', 'digital', 'combo'])->update(['type' => 'product']);

        Schema::table('items', function (Blueprint $t) {
            $t->dropUnique(['workspace_id', 'item_number']);
            $t->dropUnique(['workspace_id', 'barcode']);
            $t->dropForeign(['category_id']);
            $t->dropForeign(['brand_id']);
        });

        Schema::table('items', function (Blueprint $t) {
            $t->dropColumn([...self::NEW_COLUMNS, 'category_id', 'brand_id']);
            $t->string('type', 10)->default('product')->change();
        });

        if (Schema::hasTable('workspace_sequences')) {
            DB::table('workspace_sequences')->where('name', 'item')->delete();
        }
    }

    /** I-00001 onwards within each business, oldest first, and each business's counter left at its last. */
    private function numberItems(): void
    {
        $last = [];

        foreach (DB::table('items')->orderBy('workspace_id')->orderBy('id')->get(['id', 'workspace_id']) as $row) {
            $next = $last[$row->workspace_id] = ($last[$row->workspace_id] ?? 0) + 1;

            DB::table('items')->where('id', $row->id)->update(['item_number' => sprintf('I-%05d', $next)]);
        }

        $now = now();

        foreach ($last as $workspaceId => $value) {
            DB::table('workspace_sequences')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'name' => 'item'],
                ['last_value' => $value, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }
};
