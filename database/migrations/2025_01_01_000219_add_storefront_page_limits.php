<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase C5b: how many pages of its own - besides its contact and about
 * pages and its four policies - a business on a plan may keep on each
 * storefront (section 19.6: "according to package limits"), as the admin sets
 * it: empty for no limit. Billing Starter starts at three; the paid plans with
 * no limit, as for storefronts. A plan's versions take its limit too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('storefront_page_limit')->nullable()->after('storefront_limit');
        });

        Schema::table('package_versions', function (Blueprint $table) {
            $table->unsignedInteger('storefront_page_limit')->nullable()->after('storefront_limit');
        });

        DB::table('packages')->where('key', 'starter')->update(['storefront_page_limit' => 3]);

        foreach (DB::table('packages')->get(['id', 'storefront_page_limit']) as $package) {
            DB::table('package_versions')->where('package_id', $package->id)->update(['storefront_page_limit' => $package->storefront_page_limit]);
        }
    }

    public function down(): void
    {
        Schema::table('package_versions', function (Blueprint $table) {
            $table->dropColumn('storefront_page_limit');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('storefront_page_limit');
        });
    }
};
