<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase E (section 16.17): customer returns of a sales order's
 * delivered goods, from the order or one of its deliveries - why they came
 * back, in what condition, and what is done about it - through Draft,
 * Submitted (numbered RET-), Authorised or Rejected, Received and Completed,
 * or Cancelled. Received ones count as the order lines' returned quantities.
 * The full Sales menu gets Returns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50)->nullable();                                   // from submitting
            $table->string('status', 20)->default('draft');                            // draft | submitted | authorised | rejected | received | completed | cancelled
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();  // the sales order
            $table->foreignId('sales_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('return_date');
            $table->string('reason', 500);
            $table->string('condition', 20)->nullable();                               // unopened | opened | damaged | defective
            $table->string('resolution', 20)->nullable();                              // refund | credit_note | replacement | repair | none
            $table->text('notes')->nullable();
            $table->string('status_reason', 500)->nullable();                          // why rejected or cancelled
            $table->date('received_on')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();                                // authorised or rejected
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('sales_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_line_id')->constrained()->restrictOnDelete();   // the order line
            $table->decimal('quantity', 15, 3);
            $table->json('warranty')->nullable();                                              // the item's warranty, as the order line kept it
            $table->timestamps();

            $table->unique(['sales_return_id', 'sales_document_line_id'], 'sales_return_lines_line_unique');
        });

        // Into a menu that exists, after Invoices: a fresh install gets it from AppCatalogSeeder.
        $after = DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/invoices')->value('sort_order');

        if ($after !== null && ! DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/returns')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'sales', 'label' => 'Returns', 'icon' => 'undo-2', 'route' => '/sales/returns', 'min_edition' => 'full',
                'permission_key' => 'sales.returns.view', 'sort_order' => $after, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/returns')->delete();
        CatalogVersion::bump();

        Schema::dropIfExists('sales_return_lines');
        Schema::dropIfExists('sales_returns');
    }
};
