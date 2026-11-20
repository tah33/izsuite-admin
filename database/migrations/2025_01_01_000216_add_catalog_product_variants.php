<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase B3: product options and variants (section 19.10). A listing
 * may name up to three options - Size: S, M, L - and then groups existing
 * items of the business as its variants, its own item first: each item one
 * variant, with its own SKU, barcode, price and image from Items, and here
 * its options' values, its status, its availability without Inventory and
 * its weight. An item is a variant of one listing at most.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', function (Blueprint $table) {
            $table->json('options')->nullable()->after('custom_attributes');                 // [{name, values: [...]}], up to three
        });

        Schema::create('catalog_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('option_values');                                                  // one per option, in the options' order
            $table->string('status', 20)->default('active');                                // active | hidden
            $table->string('availability', 20)->default('available');                       // available | out_of_stock | limited
            $table->decimal('stock_quantity', 15, 3)->nullable();                           // limited: what is left to sell, kept by hand
            $table->decimal('weight', 12, 3)->nullable();                                   // in the listing's unit; none: the listing's
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['catalog_product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_variants');

        Schema::table('catalog_products', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
