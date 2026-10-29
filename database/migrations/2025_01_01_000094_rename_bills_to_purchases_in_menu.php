<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Purchases Lite's menu as Doc 09 section 4 names it: "Purchases", at
 * /purchases, where the placeholder "Bills" was. Its place stays, and the
 * sidebars build afresh. AppCatalogSeeder only adds what is missing, so it
 * cannot rename it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename(['label' => 'Bills', 'route' => '/purchases/bills'], ['label' => 'Purchases', 'route' => '/purchases']);
    }

    public function down(): void
    {
        $this->rename(['label' => 'Purchases', 'route' => '/purchases'], ['label' => 'Bills', 'route' => '/purchases/bills']);
    }

    private function rename(array $from, array $to): void
    {
        $changed = DB::table('menu_items')->where('family_key', 'purchases')->where('route', $from['route'])->update($to);

        if ($changed) {
            CatalogVersion::bump();
        }
    }
};
