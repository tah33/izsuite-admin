<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Every account must carry a role: a null role_id is an account no
            // permission check can reason about. Restrict, not null, on delete -
            // the admin panel already refuses to delete a role that still has users.
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            // Mr / Mrs / Miss / Ms / Dr - a title, not free text.
            $table->string('prefix', 20)->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('username')->nullable()->unique();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            // When they agreed, not merely that they did - a boolean cannot tell
            // you which version of the terms was in force at the time.
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('password');
            $table->string('verification_otp')->nullable();
            $table->timestamp('verification_otp_expires_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('currency', 3)->default('USD');
            $table->string('avatar')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('credit_balance')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->json('preferences')->nullable();
            $table->rememberToken();
            $table->string('affiliate_code')->nullable()->unique();
            $table->timestamp('affiliate_enabled_at')->nullable();
            $table->string('affiliate_discount_type')->nullable();
            $table->decimal('affiliate_discount_value', 10, 2)->nullable();
            $table->timestamp('affiliate_discount_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
