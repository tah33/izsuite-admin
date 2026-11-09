<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 14 Phase B1: notifications (Doc 14 section 15). Each belongs to one
 * user in one business, says what happened and where to look, and keeps how
 * it was delivered - in the app, by e-mail - with each delivery's status and
 * failure. An event reaches a user once: its key is unique per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // What set it off: "subscription.grace", "apps.activated", "members.joined".
            $table->string('trigger', 60);
            $table->string('title', 200);
            $table->text('body')->nullable();
            // The page that shows its record, in the app: "/settings?tab=subscription".
            $table->string('link', 255)->nullable();
            $table->nullableMorphs('subject');
            $table->string('event_key', 150);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'event_key']);
            $table->index(['user_id', 'workspace_id', 'read_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_notification_id')->constrained('user_notifications')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('failure')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('user_notifications');
    }
};
