<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase A: what the full Sales app adds to Sales Lite's documents and
 * menu. A business's sales teams (Settings → Sales: a name, its members, on or
 * off); on each sales document its salesperson and team, its own billing and
 * shipping address (none: the customer's), expected delivery date, shipping
 * method and terms; and in the full edition's menu the Sales Overview and a
 * Customers link (section 16.3).
 */
return new class extends Migration
{
    /** [label, icon, route, permission] of the items only the full edition shows. */
    private const ITEMS = [
        ['Overview', 'layout-dashboard', '/sales/overview', 'sales.values.view'],
        ['Customers', 'users', '/contacts?role=customer', 'contacts.view'],
    ];

    public function up(): void
    {
        Schema::create('sales_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('sales_team_members', function (Blueprint $table) {
            $table->foreignId('sales_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['sales_team_id', 'user_id']);
        });

        Schema::table('sales_documents', function (Blueprint $table) {
            $table->foreignId('salesperson_id')->nullable()->after('sales_reference')->constrained('users')->nullOnDelete();
            $table->foreignId('sales_team_id')->nullable()->after('salesperson_id')->constrained('sales_teams')->nullOnDelete();
            // The document's own addresses - line1, line2, city, state, postal_code, country - or null: the customer's.
            $table->json('billing_address')->nullable()->after('sales_team_id');
            $table->json('shipping_address')->nullable()->after('billing_address');
            $table->date('expected_delivery_date')->nullable()->after('shipping_address');
            $table->string('shipping_method', 100)->nullable()->after('expected_delivery_date');
            $table->string('shipping_terms', 100)->nullable()->after('shipping_method');
        });

        // Into a menu that exists: a fresh install gets them from AppCatalogSeeder.
        $order = (int) DB::table('menu_items')->where('family_key', 'sales')->max('sort_order');
        $now   = now();

        foreach ($order > 0 ? self::ITEMS : [] as [$label, $icon, $route, $permission]) {
            if (! DB::table('menu_items')->where('family_key', 'sales')->where('route', $route)->exists()) {
                DB::table('menu_items')->insert([
                    'section' => 'apps', 'family_key' => 'sales', 'label' => $label, 'icon' => $icon, 'route' => $route, 'min_edition' => 'full',
                    'permission_key' => $permission, 'sort_order' => $order += 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'sales')->whereIn('route', array_column(self::ITEMS, 2))->delete();

        Schema::table('sales_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salesperson_id');
            $table->dropConstrainedForeignId('sales_team_id');
            $table->dropColumn(['billing_address', 'shipping_address', 'expected_delivery_date', 'shipping_method', 'shipping_terms']);
        });

        Schema::dropIfExists('sales_team_members');
        Schema::dropIfExists('sales_teams');

        CatalogVersion::bump();
    }
};
