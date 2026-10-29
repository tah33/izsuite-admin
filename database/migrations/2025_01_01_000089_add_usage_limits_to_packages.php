<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What else a package limits (Doc 01 section 4, Doc 13) - set in the admin,
 * never in the code. Empty is no limit.
 *
 * - business_limit: how many businesses on the package one account may own.
 *   A new business starts on Billing Starter, so its 1 is "one free
 *   business per account".
 * - user_limit: how many users a business has - its owner and its staff.
 * - location_limit: how many locations a business has.
 *
 * Billing Starter starts at one of each; Standard and Premium have no limits
 * until some are set. Over a limit nothing is taken away; only adding more
 * is refused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $t) {
            $t->unsignedInteger('business_limit')->nullable()->after('storage_limit_mb');
            $t->unsignedInteger('user_limit')->nullable()->after('business_limit');
            $t->unsignedInteger('location_limit')->nullable()->after('user_limit');
        });

        DB::table('packages')->where('key', 'starter')->update(['business_limit' => 1, 'user_limit' => 1, 'location_limit' => 1]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $t) {
            $t->dropColumn(['business_limit', 'user_limit', 'location_limit']);
        });
    }
};
