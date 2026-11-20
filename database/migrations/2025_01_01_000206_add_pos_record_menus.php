<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doc 18 Phase G1: POS's pages of records (section 18.3). Point of Sale's
 * menu gains Orders, Returns and Refunds, and Cash Movements after Sessions -
 * 18.3's order - each for whoever may view them.
 */
return new class extends Migration
{
    private const ADDED = [
        ['Orders', 'receipt', '/pos/orders', 'pos.orders.view'],
        ['Returns and Refunds', 'undo-2', '/pos/returns', 'pos.returns.view'],
        ['Cash Movements', 'wallet', '/pos/cash-movements', 'pos.cash.view'],
    ];

    public function up(): void
    {
        // Into a menu that exists, after Sessions: a fresh install gets them from AppCatalogSeeder.
        $now   = now();
        $after = DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/sessions')->value('sort_order');

        if ($after !== null && ! DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/orders')->exists()) {
            DB::table('menu_items')->where('family_key', 'pos')->where('sort_order', '>', $after)->increment('sort_order', count(self::ADDED));

            foreach (self::ADDED as $index => [$label, $icon, $route, $permission]) {
                DB::table('menu_items')->insert([
                    'section' => 'apps', 'family_key' => 'pos', 'label' => $label, 'icon' => $icon, 'route' => $route, 'min_edition' => 'lite',
                    'permission_key' => $permission, 'sort_order' => $after + 1 + $index, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'pos')->whereIn('route', array_column(self::ADDED, 2))->delete();
        CatalogVersion::bump();
    }
};
