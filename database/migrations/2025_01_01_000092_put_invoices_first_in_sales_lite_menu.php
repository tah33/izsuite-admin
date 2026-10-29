<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sales Lite's menu as Doc 08 section 2 lists it: Invoices first, then
 * Quotations and Proforma Invoices. The three trade the places they hold among
 * themselves - every other item keeps its own - and the sidebars build afresh.
 * AppCatalogSeeder only adds what is missing, so it cannot move them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->order(['/sales/invoices', '/sales/quotations', '/sales/proformas']);
    }

    public function down(): void
    {
        $this->order(['/sales/quotations', '/sales/proformas', '/sales/invoices']);
    }

    /** The places $routes' items hold now, given to them in $routes' order. */
    private function order(array $routes): void
    {
        $items = DB::table('menu_items')->where('family_key', 'sales')->whereIn('route', $routes)->get(['id', 'route', 'sort_order']);

        if ($items->count() !== count($routes)) {
            return;
        }

        $places = $items->pluck('sort_order')->sort()->values();

        foreach ($routes as $index => $route) {
            DB::table('menu_items')->where('id', $items->firstWhere('route', $route)->id)->update(['sort_order' => $places[$index]]);
        }

        CatalogVersion::bump();
    }
};
