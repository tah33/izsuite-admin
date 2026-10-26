<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How much file storage each package gives a business, in MB (Doc 13 sections
 * 13.2 and 13.14) - set in the admin, never in the code. Empty is no limit.
 *
 * Billing Starter starts at 100 MB ("Limited storage"); Standard and Premium
 * have no limit until one is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $t) {
            $t->unsignedInteger('storage_limit_mb')->nullable()->after('rank');
        });

        DB::table('packages')->where('key', 'starter')->update(['storage_limit_mb' => 100]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $t) {
            $t->dropColumn('storage_limit_mb');
        });
    }
};
