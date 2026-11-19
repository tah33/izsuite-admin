<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doc 18 Phase C1: the selling screen (sections 18.3 and 18.7). Point of
 * Sale's menu gains Open POS, first, for whoever may open it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Into a menu that exists, first among POS's items: a fresh install gets it from AppCatalogSeeder.
        $now   = now();
        $first = DB::table('menu_items')->where('family_key', 'pos')->min('sort_order');

        if ($first !== null && ! DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/sell')->exists()) {
            DB::table('menu_items')->where('family_key', 'pos')->where('sort_order', '>=', $first)->increment('sort_order');
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'pos', 'label' => 'Open POS', 'icon' => 'shopping-cart', 'route' => '/pos/sell', 'min_edition' => 'lite',
                'permission_key' => 'pos.selling.open', 'sort_order' => $first, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/sell')->delete();
        CatalogVersion::bump();
    }
};
