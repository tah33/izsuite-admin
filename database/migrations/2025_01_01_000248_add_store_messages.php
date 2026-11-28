<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H4e - the contact page's enquiry form (section 19.30): a
 * customer's message to the business, kept with the requests for a
 * quotation as an enquiry of its own kind:
 *
 * - ecommerce_quote_requests.kind: quote (every request so far) or message;
 *   a message has no delivery, so delivery_country may be empty;
 * - eCommerce's Messages menu item, after Quote Requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_quote_requests', function (Blueprint $table) {
            $table->string('kind', 20)->default('quote')->after('storefront_customer_id');
            $table->char('delivery_country', 2)->nullable()->change();
        });

        // Into a menu that exists, after Quote Requests: a fresh install gets it from AppCatalogSeeder.
        $requests = DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/quote-requests')->value('sort_order');

        if ($requests !== null && ! DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/messages')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'ecommerce', 'label' => 'Messages', 'icon' => 'mail', 'route' => '/ecommerce/messages', 'min_edition' => 'lite',
                'permission_key' => 'ecommerce.orders.view', 'sort_order' => $requests + 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/messages')->delete();
        // The messages go: a request has a delivery country again.
        DB::table('ecommerce_quote_requests')->where('kind', 'message')->delete();

        Schema::table('ecommerce_quote_requests', function (Blueprint $table) {
            $table->char('delivery_country', 2)->nullable(false)->change();
            $table->dropColumn('kind');
        });

        CatalogVersion::bump();
    }
};
