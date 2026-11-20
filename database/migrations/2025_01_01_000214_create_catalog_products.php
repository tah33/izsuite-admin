<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase B1: items published online (sections 19.8 and 19.9). An item
 * of the business has at most one online listing, which adds to it what a
 * storefront shows and sells it by: its online title and link, descriptions,
 * search keywords, pictures (its gallery's files) and video links, its
 * publication status, visibility and dates, its sales method, its
 * availability without Inventory, its weight, and its SEO title and
 * description - on every storefront, or those chosen. eCommerce's menu gains
 * Catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('slug', 200);                                                    // its link: unique in the business
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();                                        // rich text
            $table->string('keywords', 500)->nullable();
            $table->string('status', 20)->default('draft');                                 // draft | scheduled | published | hidden | archived
            $table->string('visibility', 20)->default('public');                            // public | customers | link
            $table->date('available_from')->nullable();                                     // the business's days
            $table->date('available_until')->nullable();
            $table->string('sales_method', 20)->default('fixed_price');                     // fixed_price | quotation | contact | not_online
            $table->string('availability', 20)->default('available');                       // available | out_of_stock | limited
            $table->decimal('stock_quantity', 15, 3)->nullable();                           // limited: what is left to sell, kept by hand
            $table->decimal('weight', 12, 3)->nullable();
            $table->string('weight_unit', 5)->default('kg');                                // kg | g | lb | oz
            $table->json('video_urls')->nullable();
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('all_storefronts')->default(true);                              // else those in catalog_product_storefronts
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'item_id']);
            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('catalog_product_storefronts', function (Blueprint $table) {
            $table->foreignId('catalog_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->primary(['catalog_product_id', 'storefront_id']);
        });

        // Into a menu that exists, after its last item: a fresh install gets it from AppCatalogSeeder.
        $now  = now();
        $last = DB::table('menu_items')->max('sort_order');

        if ($last !== null && ! DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/catalog')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'ecommerce', 'label' => 'Catalog', 'icon' => 'library', 'route' => '/ecommerce/catalog', 'min_edition' => 'lite',
                'permission_key' => 'ecommerce.catalog.view', 'sort_order' => $last + 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/catalog')->delete();
        CatalogVersion::bump();

        Schema::dropIfExists('catalog_product_storefronts');
        Schema::dropIfExists('catalog_products');
    }
};
