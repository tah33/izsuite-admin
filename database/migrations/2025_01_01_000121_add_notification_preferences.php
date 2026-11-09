<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 14 Phase B2: notification preferences (Doc 11 section 22, Doc 12
 * section 22). What the business sends, and what each user receives, by
 * kind and channel: {"apps": {"in_app": true, "email": false}, ...}. Unset
 * means the kind's default. A notification sent by e-mail only stays out of
 * the bell.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable();
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->boolean('shown')->default(true)->after('link');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropColumn('shown');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
