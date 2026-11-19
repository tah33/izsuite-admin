<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase I2: a business's lead scoring rules (section 17.22) -
 * Settings → CRM. Each gives a lead points, more or fewer, for one thing it
 * has: its source, its location, a detail filled in, an item it is
 * interested in, its estimated value or budget, when it expects to buy, its
 * business size or type, how much has been done with it, whether it was
 * contacted, and how long it has gone without activity. An open lead's score
 * is its rules' points added up, from 0 to 100, kept with the rules that gave
 * it - its factors. A closed lead keeps the score it had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->string('factor', 20);                                                   // source | location | detail | item | value | budget | purchase_period | business_size | engagement | response | quiet

            // What the lead must have, as its factor asks.
            $table->foreignId('source_id')->nullable()->constrained('crm_options')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('option', 100)->nullable();                                      // a detail, a purchase period, words of the business size, a response
            $table->decimal('threshold', 15, 2)->nullable();                                // a value or budget in the base currency, activities, days
            $table->smallInteger('points');                                                 // -100 to 100, never 0
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('priority');            // null: not scored - no rule switched on
            $table->json('score_factors')->nullable()->after('score');                      // [{name, points}] - the rules that gave it
            $table->index(['workspace_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'score']);
            $table->dropColumn(['score', 'score_factors']);
        });

        Schema::dropIfExists('crm_scoring_rules');
    }
};
