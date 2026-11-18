<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doc 17 Phase G2: the Forecast (sections 17.3 and 17.14). CRM's menu
 * gains Forecast after Activities - Doc 17's order - for whoever may view
 * opportunities.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Into a menu that exists, after Activities: a fresh install gets it from AppCatalogSeeder.
        $now   = now();
        $after = DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/activities')->value('sort_order');

        if ($after !== null && ! DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/forecast')->exists()) {
            DB::table('menu_items')->where('family_key', 'crm')->where('sort_order', '>', $after)->increment('sort_order');
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'crm', 'label' => 'Forecast', 'icon' => 'trending-up', 'route' => '/crm/forecast', 'min_edition' => 'lite',
                'permission_key' => 'crm.opportunities.view', 'sort_order' => $after + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/forecast')->delete();
        CatalogVersion::bump();
    }
};
