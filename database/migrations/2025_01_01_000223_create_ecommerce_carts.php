<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase D3 - the cart (sections 19.13 and 19.14):
 *
 * - ecommerce_carts: a visitor's cart on a storefront, known by a token their
 *   browser keeps (only its hash here) - its status, and its currency and
 *   totals as last priced - expiring after Settings → eCommerce's days
 *   without activity;
 * - ecommerce_cart_lines: its products - a listing, its variant, the item -
 *   with their quantities and their prices as last shown;
 * - storefronts.price_list_id: with full Sales, the price list a storefront
 *   sells at;
 * - ecommerce_settings.cart_days: how long a cart is kept without activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();                                       // sha256 of the visitor's token
            $table->string('status', 12)->default('active');                                // active | converted | abandoned | expired
            $table->string('currency', 3);
            $table->decimal('subtotal', 15, 2)->default(0);                                 // as last priced, the way the store shows prices
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['storefront_id', 'status']);
        });

        Schema::create('ecommerce_cart_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->nullable();                               // as last shown
            $table->timestamps();
        });

        Schema::table('storefronts', function (Blueprint $table) {
            $table->foreignId('price_list_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
        });

        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('cart_days')->default(30)->after('order_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn('cart_days'));
        Schema::table('storefronts', fn (Blueprint $table) => $table->dropConstrainedForeignId('price_list_id'));
        Schema::dropIfExists('ecommerce_cart_lines');
        Schema::dropIfExists('ecommerce_carts');
    }
};
