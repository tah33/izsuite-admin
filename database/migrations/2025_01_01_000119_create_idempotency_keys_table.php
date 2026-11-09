<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 14 Phase A: a record created once however often its form is sent
 * (Doc 14 section 22). The frontend sends a key with each create; the first
 * answer is kept for a day against the user and key, and a repeat gets that
 * answer instead of a second record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('route', 100);
            // What was sent with the key: the same key sent with something else is refused.
            $table->char('fingerprint', 64);
            // Null while the first request is still being handled.
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->longText('response')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
