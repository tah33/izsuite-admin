<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase A1: signing in to the admin panel (section 15.2).
 *
 * admin_sign_ins keeps every sign-in to it, and every attempt that failed,
 * with when, from where and on which browser: the admin's sign-in history,
 * and how a browser is known again for the new-browser e-mail.
 *
 * users.sessions_valid_from is the moment an account's admin sessions count
 * from. Switching an admin off, changing their role or password, or
 * resetting their two-factor ends every session begun before it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_sign_ins', function (Blueprint $table) {
            $table->id();
            // The admin account it was for - null when the address is nobody's admin account.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->boolean('succeeded');
            // Why it failed: password, switched_off, two_factor or throttled (AdminSignIn::FAILURES).
            $table->string('failure', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            // A hash of the browser's own random cookie, by which it is known again.
            $table->string('device', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('sessions_valid_from')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sessions_valid_from');
        });

        Schema::dropIfExists('admin_sign_ins');
    }
};
