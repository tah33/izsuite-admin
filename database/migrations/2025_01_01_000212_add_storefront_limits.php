<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase A2: how many storefronts a business on a plan may keep
 * (section 19.4: "according to package limits"), as the admin sets it - empty
 * for no limit, like the plan's users and locations. Billing Starter starts at
 * one, as it does for those; the paid plans with no limit. A plan's versions
 * take its limit too, so the businesses held to one count against it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('storefront_limit')->nullable()->after('location_limit');
        });

        Schema::table('package_versions', function (Blueprint $table) {
            $table->unsignedInteger('storefront_limit')->nullable()->after('location_limit');
        });

        DB::table('packages')->where('key', 'starter')->update(['storefront_limit' => 1]);

        foreach (DB::table('packages')->get(['id', 'storefront_limit']) as $package) {
            DB::table('package_versions')->where('package_id', $package->id)->update(['storefront_limit' => $package->storefront_limit]);
        }
    }

    public function down(): void
    {
        Schema::table('package_versions', function (Blueprint $table) {
            $table->dropColumn('storefront_limit');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('storefront_limit');
        });
    }
};
