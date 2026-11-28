<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H4a - requests for a quotation (section 19.30):
 *
 * - ecommerce_quote_requests: a customer's request on a storefront, numbered
 *   QR-00001 - who asked (and the account they were signed in to), where it
 *   is to be delivered, the date wanted and their message - never an order,
 *   a sale or a receivable;
 * - ecommerce_quote_request_items: its products, each as it was asked for;
 * - eCommerce's Quote Requests menu item, after Abandoned Carts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30);
            $table->string('customer_name');
            $table->string('customer_email');                                                   // in small letters
            $table->string('customer_phone', 40)->nullable();
            $table->string('company')->nullable();
            $table->char('delivery_country', 2);
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_address')->nullable();
            $table->date('required_on')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('ecommerce_quote_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');                                                             // the product - and its options - as it was asked for
            $table->decimal('quantity', 15, 3);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Into a menu that exists, after Abandoned Carts: a fresh install gets it from AppCatalogSeeder.
        $carts = DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/abandoned-carts')->value('sort_order');

        if ($carts !== null && ! DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/quote-requests')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'ecommerce', 'label' => 'Quote Requests', 'icon' => 'message-square-quote', 'route' => '/ecommerce/quote-requests', 'min_edition' => 'lite',
                'permission_key' => 'ecommerce.orders.view', 'sort_order' => $carts + 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/quote-requests')->delete();
        CatalogVersion::bump();

        Schema::dropIfExists('ecommerce_quote_request_items');
        Schema::dropIfExists('ecommerce_quote_requests');
    }
};
