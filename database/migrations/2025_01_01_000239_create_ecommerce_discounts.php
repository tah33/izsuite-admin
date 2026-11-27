<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H1 - discount codes (section 19.31):
 *
 * - ecommerce_discounts: a business's discount - its name, its code (unique
 *   in the business), what it takes off (a percentage, an amount in the base
 *   currency, or free shipping), whether it is for the whole order or chosen
 *   products, the order's minimum (base currency), its first and last days,
 *   how many times it may be used in all and by one customer, and whether it
 *   is active;
 * - ecommerce_discount_products: the listings a discount for chosen products
 *   is for;
 * - ecommerce_orders.ecommerce_discount_id and promotion: the code an order
 *   was placed with, and as it was then - its name, code, what it takes off
 *   and what it took (19.31: historical orders keep their promotion);
 * - eCommerce's menu gains Discounts, after Orders, under Manage Discounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 40)->nullable();                                         // in capitals; H2's automatic ones have none
            $table->string('kind', 20);                                                     // percentage | amount | free_shipping
            $table->decimal('value', 15, 4)->nullable();                                    // a percentage, or an amount in the base currency
            $table->string('applies_to', 20)->default('order');                             // order | products
            $table->decimal('minimum', 15, 2)->nullable();                                  // the products, in the base currency
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'code']);
        });

        Schema::create('ecommerce_discount_products', function (Blueprint $table) {
            $table->foreignId('ecommerce_discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->constrained()->cascadeOnDelete();

            $table->primary(['ecommerce_discount_id', 'catalog_product_id']);
        });

        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->foreignId('ecommerce_discount_id')->nullable()->after('discount_reason')->constrained()->nullOnDelete();
            $table->json('promotion')->nullable()->after('ecommerce_discount_id');
        });

        // Into a menu that exists, after its last item: a fresh install gets it from AppCatalogSeeder.
        $now  = now();
        $last = DB::table('menu_items')->max('sort_order');

        if ($last !== null && ! DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/discounts')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'ecommerce', 'label' => 'Discounts', 'icon' => 'tag', 'route' => '/ecommerce/discounts', 'min_edition' => 'lite',
                'permission_key' => 'ecommerce.discounts.manage', 'sort_order' => $last + 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/discounts')->delete();
        CatalogVersion::bump();

        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ecommerce_discount_id');
            $table->dropColumn('promotion');
        });

        Schema::dropIfExists('ecommerce_discount_products');
        Schema::dropIfExists('ecommerce_discounts');
    }
};
