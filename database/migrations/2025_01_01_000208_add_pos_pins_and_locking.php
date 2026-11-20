<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase I1: a cashier's PIN and their register's lock (section 18.31).
 * Each user keeps their own PIN, hashed - an owner too; Settings → Point
 * of Sale says after how many minutes without use a register locks; a
 * session keeps when it was last used and whether it is locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pos_pin')->nullable()->after('two_factor_recovery_codes');    // hashed: 4 to 6 digits
            $table->timestamp('pos_pin_set_at')->nullable()->after('pos_pin');
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('lock_minutes')->nullable()->after('hold_hours'); // null: only when its cashier locks it
        });

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable()->after('opening_notes');
            $table->timestamp('locked_at')->nullable()->after('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', fn (Blueprint $table) => $table->dropColumn(['last_active_at', 'locked_at']));
        Schema::table('pos_settings', fn (Blueprint $table) => $table->dropColumn('lock_minutes'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['pos_pin', 'pos_pin_set_at']));
    }
};
