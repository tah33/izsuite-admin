<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doc 18 Phase G2: the POS Overview (sections 18.3 and 18.35). Point of
 * Sale's menu starts with Overview - 18.3's order - for whoever may view its
 * orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Into a menu that exists, before Open POS: a fresh install gets it from AppCatalogSeeder.
        $now   = now();
        $first = DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/sell')->value('sort_order');

        if ($first !== null && ! DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/overview')->exists()) {
            DB::table('menu_items')->where('family_key', 'pos')->where('sort_order', '>=', $first)->increment('sort_order');
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'pos', 'label' => 'Overview', 'icon' => 'layout-dashboard', 'route' => '/pos/overview', 'min_edition' => 'lite',
                'permission_key' => 'pos.orders.view', 'sort_order' => $first, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/overview')->delete();
        CatalogVersion::bump();
    }
};
