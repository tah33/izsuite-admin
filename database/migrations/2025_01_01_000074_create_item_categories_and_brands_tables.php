<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two lists a business sorts its items by (Doc 04 sections 6 and 7): its
 * categories - one flat list - and its brands, which show only while the
 * business has switched Brand Management on (off to begin with).
 *
 * A name is used once within a business. (The categories table already there
 * belongs to the subscriptions of the old recruitment product, hence
 * item_categories.)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['item_categories', 'item_brands'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $t->string('name', 100);
                $t->timestamps();

                $t->unique(['workspace_id', 'name']);
            });
        }

        Schema::table('workspaces', function (Blueprint $t) {
            $t->boolean('brands_enabled')->default(false)->after('tax_mode');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            $t->dropColumn('brands_enabled');
        });

        Schema::dropIfExists('item_brands');
        Schema::dropIfExists('item_categories');
    }
};
