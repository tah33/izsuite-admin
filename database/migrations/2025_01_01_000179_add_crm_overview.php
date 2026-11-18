<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase G1: the CRM Overview (sections 17.3 and 17.4). Settings →
 * CRM gains the closing-soon period - the days within which an open
 * opportunity's expected closing day makes it close soon, 30 to start - and
 * CRM's menu gains Overview, first, for whoever may view opportunities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('closing_soon_days')->default(30)->after('win_on_order');
        });

        // Into a menu that exists, first among CRM's items: a fresh install gets it from AppCatalogSeeder.
        $now   = now();
        $first = DB::table('menu_items')->where('family_key', 'crm')->min('sort_order');

        if ($first !== null && ! DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/overview')->exists()) {
            DB::table('menu_items')->where('family_key', 'crm')->where('sort_order', '>=', $first)->increment('sort_order');
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'crm', 'label' => 'Overview', 'icon' => 'layout-dashboard', 'route' => '/crm/overview', 'min_edition' => 'lite',
                'permission_key' => 'crm.opportunities.view', 'sort_order' => $first, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/overview')->delete();
        CatalogVersion::bump();

        Schema::table('crm_settings', fn (Blueprint $table) => $table->dropColumn('closing_soon_days'));
    }
};
