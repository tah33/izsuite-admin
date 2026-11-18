<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase D1: opportunities themselves (sections 17.12 to 17.14). An
 * opportunity gains the person to deal with, its competitors, tags and
 * interested items, and keeps each stage it is at - when it came, who moved
 * it there and when it left - so the time spent at each one is known. The
 * opportunities there are already start at the stage they are at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->string('contact_person', 100)->nullable()->after('contact_id');
            $table->json('competitors')->nullable()->after('next_action');
            $table->json('tags')->nullable()->after('competitors');
        });

        Schema::create('crm_opportunity_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();           // none: a name typed in
            $table->string('name');                                                              // the item's name as it was, or the one typed
            $table->decimal('quantity', 15, 3)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_stage_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_stage_id')->constrained()->restrictOnDelete();               // a stage visited is switched off, never removed
            $table->timestamp('entered_at');
            $table->timestamp('left_at')->nullable();                                          // none: where it is now
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['crm_opportunity_id', 'entered_at']);
        });

        DB::table('crm_opportunities')->orderBy('id')->select(['id', 'stage_id', 'stage_entered_at', 'created_at', 'created_by'])->each(function ($opportunity) {
            $at = $opportunity->stage_entered_at ?? $opportunity->created_at ?? now();

            DB::table('crm_stage_visits')->insert([
                'crm_opportunity_id' => $opportunity->id,
                'crm_stage_id'       => $opportunity->stage_id,
                'entered_at'         => $at,
                'entered_by'         => $opportunity->created_by,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        });

        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/opportunities')->update(['permission_key' => 'crm.opportunities.view', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/opportunities')->update(['permission_key' => null]);

        Schema::dropIfExists('crm_stage_visits');
        Schema::dropIfExists('crm_opportunity_items');

        Schema::table('crm_opportunities', fn (Blueprint $table) => $table->dropColumn(['contact_person', 'competitors', 'tags']));
    }
};
