<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase I4: a business's own fields for its leads and opportunities
 * (sections 17.6, 17.12 and 17.26) - Settings → CRM, in their order - each
 * text, a number, a date, a choice of its options, or yes or no, optional or
 * required, and switched on or off. A lead's or opportunity's values are
 * kept with it, by field id; one switched off keeps them, hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('record', 20);                                                   // lead | opportunity
            $table->string('name', 100);
            $table->string('type', 20);                                                     // text | number | date | choice | yes_no
            $table->json('options')->nullable();                                            // a choice's, in their order
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'record', 'name']);
            $table->index(['workspace_id', 'record', 'position']);
        });

        foreach (['crm_leads', 'crm_opportunities'] as $records) {
            Schema::table($records, fn (Blueprint $table) => $table->json('custom_fields')->nullable()->after('notes'));    // {field id: value}
        }
    }

    public function down(): void
    {
        foreach (['crm_leads', 'crm_opportunities'] as $records) {
            Schema::table($records, fn (Blueprint $table) => $table->dropColumn('custom_fields'));
        }

        Schema::dropIfExists('crm_custom_fields');
    }
};
