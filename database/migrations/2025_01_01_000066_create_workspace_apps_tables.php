<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which apps each business has turned on (Doc 01 sections 8, 9 and 13).
 *
 * workspaces.package_id is the business's plan; every existing and every new
 * one starts on Billing Starter. nav_version goes up by one whenever its
 * sidebar must be rebuilt - it is part of the sidebar's cache key, so nothing
 * needs clearing by hand.
 *
 * workspaces.app_id goes. A business now has many apps, one row per family in
 * workspace_apps, instead of one app of its own.
 *
 * workspace_apps holds one row per business and family - UNIQUE (workspace_id,
 * family_key) - naming the edition that is on, so Lite and Full can never both
 * be: turning Full on changes the row in place. Turning an app off keeps the
 * row as inactive, so its records stay readable and turning it on again finds
 * them.
 *
 * workspace_app_logs records every change, for history and support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('user_id')->constrained('packages')->restrictOnDelete();
            $table->unsignedInteger('nav_version')->default(0)->after('package_id');
        });

        DB::table('workspaces')->update([
            'package_id' => DB::table('packages')->where('key', 'starter')->value('id'),
        ]);

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropForeign(['app_id']);
            $table->dropColumn('app_id');
        });

        Schema::create('workspace_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('family_key', 100);
            $table->foreignId('app_id')->constrained('apps')->restrictOnDelete();
            $table->string('status', 10)->default('active');
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'family_key']);
        });

        Schema::create('workspace_app_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_id')->constrained('apps')->restrictOnDelete();
            $table->foreignId('from_app_id')->nullable()->constrained('apps')->restrictOnDelete();
            $table->string('action', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_app_logs');
        Schema::dropIfExists('workspace_apps');

        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('app_id')->nullable()->after('name')->constrained('apps')->nullOnDelete();
            $table->dropForeign(['package_id']);
            $table->dropColumn(['package_id', 'nav_version']);
        });
    }
};
