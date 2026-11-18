<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase I1: a business's lead assignment rules (section 17.9) -
 * Settings → CRM - in their order. A lead added without an owner goes to the
 * first rule switched on that fits: it asks for what the lead has (its
 * source, location, sales team, country, an interested item - each left out
 * for any) and gives it to a person or to a sales team's members, by turns
 * or to whoever has the fewest open leads. Its turn is kept: the member it
 * gave a lead to last.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            // What a lead must have; each left out for any.
            $table->foreignId('source_id')->nullable()->constrained('crm_options')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('sales_team_id')->nullable()->constrained('sales_teams')->nullOnDelete();
            $table->char('country', 2)->nullable();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();

            // Whom it goes to: a person, or a sales team's members by a method.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('sales_teams')->nullOnDelete();
            $table->string('method', 20)->nullable();
            $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
            $table->index(['workspace_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_assignment_rules');
    }
};
