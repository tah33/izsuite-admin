<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('logo_url')->nullable();
            $table->string('category');
            $table->string('status')->default('available');
            $table->boolean('is_active')->default(true);

            // Editions: rows sharing a family_key are tiers of one product
            // (e.g. lite / pro / max). sort_order ranks them inside the family,
            // so an upgrade is any sibling with a higher sort_order.
            $table->string('family_key', 100)->index();
            $table->string('edition', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['family_key', 'edition']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
