<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase A2 (sections 17.13, 17.14 and 17.24): a business's
 * opportunity pipeline - its stages in order, each with the probability an
 * opportunity in it starts with and whether it is open, won or lost - and a
 * sales team's manager, now that teams are CRM's as well as Sales'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('crm_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('position')->default(1);
            $table->unsignedTinyInteger('probability')->default(0);      // the default, 0 to 100
            $table->string('category', 10)->default('open');            // open | won | lost
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['crm_pipeline_id', 'position']);
        });

        Schema::table('sales_teams', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('name')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_teams', fn (Blueprint $table) => $table->dropConstrainedForeignId('manager_id'));
        Schema::dropIfExists('crm_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
