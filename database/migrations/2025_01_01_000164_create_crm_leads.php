<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase B1: CRM's leads (sections 17.6 to 17.9) - who they are, where
 * they came from, who follows them up, and where they stand - and the Leads
 * menu item needing the leads' View (section 17.30).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');                                                        // the person, or the business when nobody is named
            $table->string('business_name')->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('source_id')->nullable()->constrained('crm_options')->nullOnDelete();
            $table->string('source_details')->nullable();                                  // a referral's name, a form, a platform or a reference
            $table->string('status', 20)->default('new');                                  // new | assigned | contacted | qualified | unqualified | converted | archived
            $table->string('status_reason', 500)->nullable();                              // why unqualified or archived
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // none: in the queue
            $table->foreignId('sales_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('priority', 10)->nullable();                                    // low | medium | high
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->date('expected_purchase_date')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('address_state', 100)->nullable();
            $table->string('address_postal_code', 20)->nullable();
            $table->char('address_country', 2)->nullable();
            $table->string('preferred_language', 10)->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->nullable();                                  // when its owner was given it
            $table->timestamp('contacted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'owner_id']);
        });

        // Into a menu that exists: a fresh install gets it from AppCatalogSeeder.
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/leads')->update(['permission_key' => 'crm.leads.view', 'updated_at' => now()]);
        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/leads')->update(['permission_key' => null]);
        CatalogVersion::bump();

        Schema::dropIfExists('crm_leads');
    }
};
