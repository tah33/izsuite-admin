<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business sidebar, as data (Doc 01 section 6).
 *
 * section is core (Dashboard, Contacts, ...), apps (the pages inside an app
 * family) or bottom (Apps Center, Settings). An app's own entry is not a row
 * here - its name, icon and place come from the apps table - so apps rows are
 * the items inside a family.
 *
 * min_edition: lite shows the item in both editions, full only in the Full
 * one. Core and bottom items have neither a family nor an edition.
 *
 * show_if names a rule in code (App\Services\User\Apps\ShowIfRules), never an
 * expression: a rule checks real data, and the admin only picks one.
 *
 * Hide an item with is_active rather than deleting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('section', 10);
            $table->string('family_key', 100)->nullable();
            $table->string('min_edition', 10)->nullable();
            $table->string('label');
            $table->string('icon', 100)->nullable();
            $table->string('route')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('permission_key', 150)->nullable();
            $table->string('show_if', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['section', 'family_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
