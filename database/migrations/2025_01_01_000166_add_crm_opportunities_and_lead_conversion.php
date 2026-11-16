<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase C1: opportunities (section 17.12), as converting a lead makes
 * them - their own pages and changes come with Phase D - and what a
 * converted lead keeps of its conversion (section 17.11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();   // the lead it was converted from
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('crm_stages')->restrictOnDelete();
            $table->string('status', 10)->default('open');                                       // open | won | lost
            $table->unsignedTinyInteger('probability');                                         // %, the stage's to start
            $table->decimal('expected_value', 15, 2);                                           // before tax
            $table->char('currency', 3);
            $table->date('expected_close_date');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('crm_options')->nullOnDelete();
            $table->string('priority', 10)->nullable();
            $table->text('requirements')->nullable();                                           // the customer's need
            $table->string('next_action')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('stage_entered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'contact_id']);
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('qualification_notes')->constrained()->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->after('contact_id')->constrained('crm_opportunities')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('contacted_at');
            $table->foreignId('converted_by')->nullable()->after('converted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_by');
            $table->dropColumn('converted_at');
            $table->dropConstrainedForeignId('opportunity_id');
            $table->dropConstrainedForeignId('contact_id');
        });

        Schema::dropIfExists('crm_opportunities');
    }
};
