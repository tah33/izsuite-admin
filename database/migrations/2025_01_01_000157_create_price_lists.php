<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase F1 (section 16.19): price lists. A list has a currency, the
 * days it holds, the customers and locations it is for (all, or chosen) and a
 * priority; its rules price an item, a category, a brand or everything, from a
 * quantity up: a fixed price, a percentage off the item's price, or a markup
 * on its cost. A sales line keeps the list and rule that priced it. The full
 * Sales menu gets Price Lists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('all_customers')->default(true);
            $table->boolean('all_locations')->default(true);
            $table->unsignedSmallInteger('priority')->default(10);              // lower first
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('price_list_contacts', function (Blueprint $table) {
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();

            $table->primary(['price_list_id', 'contact_id']);
        });

        Schema::create('price_list_locations', function (Blueprint $table) {
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            $table->primary(['price_list_id', 'location_id']);
        });

        Schema::create('price_list_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->string('target', 10);                                         // item | category | brand | all
            $table->foreignId('item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('item_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('item_brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('min_quantity', 15, 3)->default(1);
            $table->string('method', 10);                                         // fixed | discount | markup
            $table->decimal('value', 15, 4);                                      // a price, or a percentage
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::table('sales_document_lines', function (Blueprint $table) {
            // The list and rule that priced it, as they were then: later changes reprice nothing.
            $table->foreignId('price_list_id')->nullable()->after('deposit_invoice_id')->constrained()->nullOnDelete();
            $table->foreignId('price_list_rule_id')->nullable()->after('price_list_id')->constrained()->nullOnDelete();
        });

        // Into a menu that exists, after Payments Received: a fresh install gets it from AppCatalogSeeder.
        $after = DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/payments')->value('sort_order');

        if ($after !== null && ! DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/price-lists')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'sales', 'label' => 'Price Lists', 'icon' => 'tags', 'route' => '/sales/price-lists', 'min_edition' => 'full',
                'permission_key' => 'sales.price_lists.view', 'sort_order' => $after, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/price-lists')->delete();
        CatalogVersion::bump();

        Schema::table('sales_document_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_list_rule_id');
            $table->dropConstrainedForeignId('price_list_id');
        });

        Schema::dropIfExists('price_list_rules');
        Schema::dropIfExists('price_list_locations');
        Schema::dropIfExists('price_list_contacts');
        Schema::dropIfExists('price_lists');
    }
};
