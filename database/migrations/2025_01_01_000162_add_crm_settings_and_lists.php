<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase A1 (sections 17.3, 17.5, 17.8, 17.16, 17.17 and 17.26): CRM's
 * settings - its mode, leads and opportunities or opportunities only - and
 * its lists of lead sources, lost reasons and activity types, each starting
 * with Doc 17's examples, a starting one keeping izSuite's key for it. The
 * CRM menu: Deals becomes Opportunities, Leads shows while the mode uses
 * them, and Customers opens Contacts filtered to customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mode', 20)->default('leads');                // leads | opportunities
            $table->timestamps();
        });

        Schema::create('crm_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                                  // lead_source | lost_reason | activity_type
            $table->string('key', 30)->nullable();                       // izSuite's own, for a starting one
            $table->string('name', 100);
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'kind', 'name']);
            $table->unique(['workspace_id', 'kind', 'key']);
            $table->index(['workspace_id', 'kind', 'position']);
        });

        // Into a menu that exists: a fresh install gets them from AppCatalogSeeder.
        $now = now();

        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/deals')->update(['label' => 'Opportunities', 'route' => '/crm/opportunities', 'updated_at' => $now]);
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/leads')->update(['show_if' => 'crm_uses_leads', 'updated_at' => $now]);

        $order = DB::table('menu_items')->where('family_key', 'crm')->max('sort_order');

        if ($order !== null && ! DB::table('menu_items')->where('family_key', 'crm')->where('route', '/contacts?role=customer')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'crm', 'label' => 'Customers', 'icon' => 'users', 'route' => '/contacts?role=customer', 'min_edition' => 'lite',
                'permission_key' => 'contacts.view', 'sort_order' => $order + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/contacts?role=customer')->delete();
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/leads')->update(['show_if' => null]);
        DB::table('menu_items')->where('family_key', 'crm')->where('route', '/crm/opportunities')->update(['label' => 'Deals', 'route' => '/crm/deals']);
        CatalogVersion::bump();

        Schema::dropIfExists('crm_options');
        Schema::dropIfExists('crm_settings');
    }
};
