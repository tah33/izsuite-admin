<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase A1: eCommerce comes on. The Apps Center offers it - it was
 * coming soon - where an admin has not changed it since, needing Sales Lite.
 *
 * Settings → eCommerce keeps the business's own rules for its storefronts
 * (section 19.37): whether a guest may check out, whether a customer's
 * account waits for their e-mail to be verified, whether prices are shown
 * with tax or without it, and the prefix of its online orders' numbers.
 *
 * Its storefronts (section 19.4): each a name, a code - its address, unique
 * across izSuite - a status, its default and other languages, currencies and
 * countries, its default location, and who customers ask for help. Only a
 * Live one takes orders. eCommerce's menu gains Storefronts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('guest_checkout')->default(true);
            $table->boolean('verify_customer_email')->default(true);
            $table->string('price_display', 20)->default('with_tax');                        // with_tax | without_tax
            $table->string('order_prefix', 20)->default('EC-');
            $table->timestamps();
        });

        Schema::create('storefronts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 40)->unique();                                           // its address: unique across izSuite
            $table->string('status', 20)->default('draft');                                 // draft | live | maintenance | suspended | archived
            $table->string('default_language', 10);
            $table->json('languages');
            $table->char('currency', 3);
            $table->json('currencies');
            $table->json('countries');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('support_email')->nullable();
            $table->string('support_phone', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        $now = now();
        $app = DB::table('apps')->where('app_key', 'ecommerce')->first();

        if ($app) {
            DB::table('apps')->where('id', $app->id)->where('status', 'coming_soon')->update(['status' => 'available', 'updated_at' => $now]);

            if (! DB::table('app_dependencies')->where('app_id', $app->id)->where('requires_family', 'sales')->exists()) {
                DB::table('app_dependencies')->insert(['app_id' => $app->id, 'requires_family' => 'sales', 'min_edition' => 'lite', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // Into a menu that exists, after its last item: a fresh install gets it from AppCatalogSeeder.
        $last = DB::table('menu_items')->max('sort_order');

        if ($last !== null && ! DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/storefronts')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'ecommerce', 'label' => 'Storefronts', 'icon' => 'store', 'route' => '/ecommerce/storefronts', 'min_edition' => 'lite',
                'permission_key' => 'ecommerce.store.view', 'sort_order' => $last + 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'ecommerce')->where('route', '/ecommerce/storefronts')->delete();

        $app = DB::table('apps')->where('app_key', 'ecommerce')->first();

        if ($app) {
            DB::table('app_dependencies')->where('app_id', $app->id)->where('requires_family', 'sales')->delete();
            DB::table('apps')->where('id', $app->id)->where('status', 'available')->update(['status' => 'coming_soon', 'updated_at' => now()]);
        }

        CatalogVersion::bump();

        Schema::dropIfExists('storefronts');
        Schema::dropIfExists('ecommerce_settings');
    }
};
