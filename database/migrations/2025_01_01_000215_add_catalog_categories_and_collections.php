<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase B2: categories, collections and filters (section 19.11).
 *
 * An item category - the shared Items' own - gets what a storefront shows of
 * it: its link, words, picture and SEO, whether its menu lists it, and where.
 * Collections are the business's own sets of products, for a promotion, a
 * season, a brand or a theme: chosen products, in their order - or, for a
 * brand, every listed product of it - each with its link, words, picture,
 * days and SEO. A listing gains custom attributes - Material: Cotton - for
 * the storefront's filters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_category_id')->unique()->constrained('item_categories')->cascadeOnDelete();
            $table->string('slug', 200);
            $table->text('description')->nullable();                                        // rich text
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('in_menu')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('catalog_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 200);
            $table->string('kind', 20);                                                     // promotion | season | brand | theme
            $table->foreignId('item_brand_id')->nullable()->constrained('item_brands')->nullOnDelete();
            $table->text('description')->nullable();                                        // rich text
            $table->string('status', 20)->default('active');                                // active | hidden
            $table->date('available_from')->nullable();                                     // the business's days
            $table->date('available_until')->nullable();
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('catalog_collection_products', function (Blueprint $table) {
            $table->foreignId('catalog_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['catalog_collection_id', 'catalog_product_id']);
        });

        Schema::table('catalog_products', function (Blueprint $table) {
            $table->json('custom_attributes')->nullable()->after('video_urls');             // [{name, value}]: for the storefront's filters
        });
    }

    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table) {
            $table->dropColumn('custom_attributes');
        });

        Schema::dropIfExists('catalog_collection_products');
        Schema::dropIfExists('catalog_collections');
        Schema::dropIfExists('catalog_categories');
    }
};
