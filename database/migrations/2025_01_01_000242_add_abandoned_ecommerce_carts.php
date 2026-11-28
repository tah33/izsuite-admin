<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H3a - abandoned carts (section 19.32):
 *
 * - ecommerce_carts: its customer, as checkout learns them - the account
 *   signed in, the name and e-mail - and whether they agreed to marketing by
 *   e-mail; and when it was last found abandoned (status abandoned, kept once
 *   it is used again or placed);
 * - ecommerce_settings.abandoned_hours: how long a cart whose customer is
 *   known goes unused before it is Abandoned;
 * - eCommerce's Abandoned Carts menu item, after Orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_carts', function (Blueprint $table) {
            $table->foreignId('storefront_customer_id')->nullable()->after('storefront_id')->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable()->after('storefront_customer_id');
            $table->string('customer_email')->nullable()->after('customer_name');               // in small letters
            $table->boolean('marketing')->default(false)->after('customer_email');              // agreed to marketing by e-mail
            $table->timestamp('abandoned_at')->nullable()->after('last_activity_at');

            $table->index(['workspace_id', 'abandoned_at']);
        });

        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('abandoned_hours')->default(24)->after('cart_days');
        });

        // Into a menu that exists, after Orders: a fresh install gets it from AppCatalogSeeder.
        $orders = DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/orders')->value('sort_order');

        if ($orders !== null && ! DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/abandoned-carts')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'ecommerce', 'label' => 'Abandoned Carts', 'icon' => 'shopping-cart', 'route' => '/ecommerce/abandoned-carts', 'min_edition' => 'lite',
                'permission_key' => 'ecommerce.orders.view', 'sort_order' => $orders + 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/abandoned-carts')->delete();
        CatalogVersion::bump();

        // The code before knows no abandoned cart: such a cart is active again.
        DB::table('ecommerce_carts')->where('status', 'abandoned')->update(['status' => 'active']);

        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn('abandoned_hours'));

        Schema::table('ecommerce_carts', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'abandoned_at']);
            $table->dropConstrainedForeignId('storefront_customer_id');
            $table->dropColumn(['customer_name', 'customer_email', 'marketing', 'abandoned_at']);
        });
    }
};
