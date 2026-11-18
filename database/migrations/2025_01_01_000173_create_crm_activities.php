<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase E1: CRM activities - a call, meeting, task, e-mail,
 * follow-up, demonstration, site visit or other, for a lead, an opportunity
 * or a contact, given to a member and due at a day and time; planned, then
 * completed (with an outcome where its type needs one) or cancelled. Each
 * activity type says whether completing one needs an outcome, and CRM's
 * menu gains Activities after Opportunities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('type_id')->constrained('crm_options');
            $table->string('subject');
            // What it is for: a lead, an opportunity or a contact. A lead's goes with it into its opportunity, keeping both.
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->cascadeOnDelete();
            // Its record's location, for who may see it; none for a contact's.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at');                                                         // UTC
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('priority', 10)->nullable();                                         // low | medium | high
            $table->string('place')->nullable();
            $table->string('call_direction', 10)->nullable();                                   // inbound | outbound, for a call
            $table->text('description')->nullable();
            $table->string('status', 10)->default('planned');                                   // planned | completed | cancelled
            $table->text('outcome')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('follow_up_of')->nullable()->constrained('crm_activities')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'due_at']);
            $table->index(['workspace_id', 'assigned_to', 'status']);
        });

        Schema::table('crm_options', function (Blueprint $table) {
            $table->boolean('needs_outcome')->default(false)->after('is_active');
        });

        // izSuite's own types that are worth an outcome once done.
        DB::table('crm_options')->where('kind', 'activity_type')->whereIn('key', \App\Models\User\CrmOption::NEEDS_OUTCOME)->update(['needs_outcome' => true]);

        // Into a menu that exists, after Opportunities: a fresh install gets it from AppCatalogSeeder.
        $now   = now();
        $after = DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/opportunities')->value('sort_order');

        if ($after !== null && ! DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/activities')->exists()) {
            DB::table('menu_items')->where('family_key', 'crm')->where('sort_order', '>', $after)->increment('sort_order');
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'crm', 'label' => 'Activities', 'icon' => 'calendar-check', 'route' => '/crm/activities', 'min_edition' => 'lite',
                'permission_key' => 'crm.activities.view', 'sort_order' => $after + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/activities')->delete();
        CatalogVersion::bump();

        Schema::table('crm_options', fn (Blueprint $table) => $table->dropColumn('needs_outcome'));
        Schema::dropIfExists('crm_activities');
    }
};
