<?php

use App\Support\AdminRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase A2: the admin panel's audit log (section 15.26), apart from
 * the businesses' Activity Logs. Each entry keeps the admin and their role as
 * they were then, the action and its record, previous and new values, the
 * reason and approval a critical action asks for (section 15.25), the result,
 * and where it came from. Nothing in the panel changes or deletes one.
 *
 * Also the starting admin roles of section 15.3 (AdminRoles), on a database
 * that has its roles already. A new one gets them from RoleSeeder, after the
 * Super Admin, who must hold id 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // As they were then: a renamed or deleted admin's entries still say who.
            $table->string('admin_name')->nullable();
            $table->string('role_name')->nullable();
            $table->string('action', 30);
            $table->string('description', 500);
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            // Each setting's previous and new value (SettingChanges). Secrets are never kept.
            $table->json('changes')->nullable();
            $table->text('reason')->nullable();
            // How a critical action was approved: "password", the admin's password given again.
            $table->string('approval', 30)->nullable();
            // succeeded, failed or refused.
            $table->string('result', 20)->default('succeeded');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            // A hash of the session it came from, so one session's entries can be told together.
            $table->string('session', 16)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index('action');
            $table->index('created_at');
        });

        if (DB::table('roles')->where('slug', 'super-admin')->exists()) {
            AdminRoles::createMissing();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
