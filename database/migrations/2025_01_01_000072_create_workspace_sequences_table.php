<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The running counters a business numbers its records with - "contact" for its
 * Contact Numbers (C-00001), and later its documents' numbers - one row per
 * business and counter, holding the last number given out.
 *
 * A counter only goes up: a number whose record was deleted is not given out
 * again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_sequences');
    }
};
