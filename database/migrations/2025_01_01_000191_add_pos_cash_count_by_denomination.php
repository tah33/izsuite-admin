<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase B2b: counting a POS session's cash by notes and coins
 * (section 18.27). Settings → Point of Sale gains whether the business
 * counts that way, and each currency's notes and coins as the business set
 * them - one it has not set starts with izSuite's usual ones. A Z report
 * keeps the count: how many of each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->boolean('counts_denominations')->default(false)->after('variance_limit');
            $table->json('denominations')->nullable()->after('counts_denominations');      // {"USD": ["100.00", ...]}, the largest first
        });

        Schema::table('pos_session_closings', function (Blueprint $table) {
            $table->json('denominations')->nullable()->after('counted_cash');               // [{value, count, amount}], counted that way
        });
    }

    public function down(): void
    {
        Schema::table('pos_session_closings', function (Blueprint $table) {
            $table->dropColumn('denominations');
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn(['counts_denominations', 'denominations']);
        });
    }
};
