<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 14 Phase D: comments (section 14.14) on sales documents, purchases
 * and expenses. Each is written by one user of the business. It keeps the
 * users it mentions, whom it notified through Phase B's notifications.
 * Comments are never printed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->json('mentions')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'commentable_type', 'commentable_id'], 'record_comments_record_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_comments');
    }
};
