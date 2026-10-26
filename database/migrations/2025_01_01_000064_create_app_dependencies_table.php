<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an app needs turned on before it can be: Inventory needs Purchases, at
 * least its Lite edition.
 *
 * A requirement names a family, not an app, so either edition meets a Lite
 * requirement and only Full meets a Full one. An app cannot require its own
 * family, and requirements cannot loop - the admin panel refuses both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('requires_family', 100);
            $table->string('min_edition', 10)->default('lite');
            $table->timestamps();

            $table->unique(['app_id', 'requires_family']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_dependencies');
    }
};
