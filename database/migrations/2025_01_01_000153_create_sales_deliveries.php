<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase C: deliveries (section 16.10). A delivery note (DN-) belongs to
 * one sales order and lists how much of its lines goes out: where it goes,
 * who carries it (carrier, tracking number, vehicle or driver), who received
 * it and the day it arrived. Draft, Ready - numbered then - Delivered or
 * Cancelled; a delivered one that left lines short can have a backorder made
 * of the rest (backorder_of). No stock moves: Inventory is not written yet.
 * The full Sales menu gets Deliveries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();   // its sales order
            $table->string('number', 50)->nullable();
            $table->string('status', 20)->default('draft');                            // draft | ready | delivered | cancelled
            $table->date('delivery_date');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->json('shipping_address')->nullable();                              // null: the order's
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('vehicle', 100)->nullable();                                // vehicle or driver
            $table->string('recipient_name', 150)->nullable();
            $table->text('notes')->nullable();
            $table->date('delivered_on')->nullable();                                  // the day it arrived
            $table->string('status_reason', 500)->nullable();                          // why it was cancelled
            $table->foreignId('backorder_of')->nullable()->constrained('sales_deliveries')->nullOnDelete();
            $table->timestamp('readied_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'status', 'delivery_date']);
        });

        Schema::create('sales_delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_line_id')->constrained()->restrictOnDelete();  // the order's line
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->unique(['sales_delivery_id', 'sales_document_line_id'], 'sales_delivery_lines_line_unique');
        });

        // Into a menu that exists, after Sales Orders: a fresh install gets it from AppCatalogSeeder.
        $after = DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/orders')->value('sort_order');

        if ($after !== null && ! DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/deliveries')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'sales', 'label' => 'Deliveries', 'icon' => 'truck', 'route' => '/sales/deliveries', 'min_edition' => 'full',
                'permission_key' => 'sales.deliveries.view', 'sort_order' => $after, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/deliveries')->delete();
        CatalogVersion::bump();

        Schema::dropIfExists('sales_delivery_lines');
        Schema::dropIfExists('sales_deliveries');
    }
};
