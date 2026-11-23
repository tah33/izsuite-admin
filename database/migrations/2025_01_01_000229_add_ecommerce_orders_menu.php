<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doc 19 Phase F1 - the business's online orders (sections 19.18 and
 * 19.19): eCommerce's menu gains Orders, after Catalog, under View Orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Into a menu that exists, after its last item: a fresh install gets it from AppCatalogSeeder.
        $now  = now();
        $last = DB::table('menu_items')->max('sort_order');

        if ($last !== null && ! DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/orders')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'ecommerce', 'label' => 'Orders', 'icon' => 'receipt', 'route' => '/ecommerce/orders', 'min_edition' => 'lite',
                'permission_key' => 'ecommerce.orders.view', 'sort_order' => $last + 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/orders')->delete();
        CatalogVersion::bump();
    }
};
