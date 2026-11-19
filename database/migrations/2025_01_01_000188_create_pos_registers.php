<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase A2: POS registers (section 18.5). Each belongs to one
 * location and sells in one currency, with its price list (the full Sales
 * app's), the cash account its drawer's cash goes into, its receipt number
 * prefix, the payment methods it takes and its cashiers, and Active,
 * Inactive or Maintenance. Point of Sale's menu gains Registers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->foreignId('location_id')->constrained('locations');
            $table->char('currency', 3);
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->string('receipt_prefix', 20);
            $table->string('status', 20)->default('active');                                // active | inactive | maintenance
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
            $table->unique(['workspace_id', 'code']);
        });

        Schema::create('pos_register_payment_methods', function (Blueprint $table) {
            $table->foreignId('pos_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_payment_method_id')->constrained()->cascadeOnDelete();
            $table->primary(['pos_register_id', 'pos_payment_method_id']);
        });

        Schema::create('pos_register_cashiers', function (Blueprint $table) {
            $table->foreignId('pos_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['pos_register_id', 'user_id']);
        });

        // Into a menu that exists, after its last item: a fresh install gets it from AppCatalogSeeder.
        $now  = now();
        $last = DB::table('menu_items')->max('sort_order');

        if ($last !== null && ! DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/registers')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'pos', 'label' => 'Registers', 'icon' => 'monitor', 'route' => '/pos/registers', 'min_edition' => 'lite',
                'permission_key' => 'pos.registers.view', 'sort_order' => $last + 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/registers')->delete();
        CatalogVersion::bump();

        Schema::dropIfExists('pos_register_cashiers');
        Schema::dropIfExists('pos_register_payment_methods');
        Schema::dropIfExists('pos_registers');
    }
};
