<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 12 Phase E1: personal settings (section 22) and active sessions
 * (section 23).
 *
 * An account gets a preferred language, and its time zone becomes a choice:
 * empty means each business's own. Nobody could choose one before, so the
 * "UTC" every account holds is the column's old default, not a choice, and
 * is cleared.
 *
 * Each sign-in keeps the IP address and browser it came from, so a user can
 * tell their devices apart. Sign-ins from before have neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 10)->nullable()->after('phone');
            $table->string('timezone')->nullable()->default(null)->change();
        });

        DB::table('users')->where('timezone', 'UTC')->update(['timezone' => null]);

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('abilities');
            $table->string('user_agent', 512)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });

        DB::table('users')->whereNull('timezone')->update(['timezone' => 'UTC']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('language');
            $table->string('timezone')->default('UTC')->nullable(false)->change();
        });
    }
};
