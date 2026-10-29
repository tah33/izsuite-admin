<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchases Lite (Doc 09): what a business buys from its suppliers.
 *
 * - purchase_settings: Settings > Purchases (Doc 11 section 16) - a supplier's
 *   payment terms when they have none of their own, the notes a new purchase
 *   starts with, and whether recording one checks for a supplier invoice
 *   entered before. One row per business, made when first saved.
 * - purchases: the header. A draft has no number; it gets one when it is
 *   recorded (Doc 09 section 10), and keeps the business and the supplier as
 *   they were then. Totals are SalesCalculator's - the same arithmetic as a
 *   sale - in the purchase's currency and, at its exchange rate, in the base
 *   currency. supplier_invoice_number and _date are the supplier's own; an
 *   exact duplicate recorded anyway keeps why (duplicate_reason).
 * - purchase_lines: an item or a free description each, with the tax as it
 *   was when the line was saved - recoverable or not, for the Tax Summary.
 *
 * Contacts, items, tax rates and locations a purchase uses cannot be deleted
 * (see their services); the nullOnDelete foreign keys only let a business be
 * deleted with everything in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('payment_terms')->default(30);
            $table->text('notes')->nullable();
            $table->boolean('duplicate_check')->default(true);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50)->nullable();
            $table->string('status', 20)->default('draft');              // draft | recorded | void
            $table->string('payment_status', 20)->default('not_applicable');
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('document_date');                               // the purchase date
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('payment_terms')->nullable();   // the days the due date came from
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 20, 8)->default(1);         // base currency per unit of `currency`
            $table->string('price_tax_mode', 10)->default('exclusive');
            $table->string('tax_rounding', 10)->default('line');
            $table->string('supplier_invoice_number', 100)->nullable();
            $table->date('supplier_invoice_date')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('discount_type', 10)->nullable();             // percent | amount
            $table->decimal('discount_value', 15, 4)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2)->default(0);
            $table->decimal('base_grand_total', 15, 2)->default(0);
            $table->decimal('base_tax_total', 15, 2)->default(0);
            $table->decimal('base_net_total', 15, 2)->default(0);
            $table->json('taxes')->nullable();                           // the tax breakdown, one entry per rate
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('withholding_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('business_snapshot')->nullable();
            $table->json('supplier_snapshot')->nullable();
            $table->json('template_snapshot')->nullable();
            $table->string('status_reason', 500)->nullable();            // why it was voided
            $table->string('duplicate_reason', 500)->nullable();         // why an exact duplicate was recorded
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'document_date']);
            $table->index(['workspace_id', 'contact_id', 'supplier_invoice_number']);
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 15, 4)->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);                // quantity × price, less its discount
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tax_name', 100)->nullable();
            $table->string('tax_code', 20)->nullable();
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->string('tax_treatment', 20)->nullable();
            $table->string('tax_recoverability', 20)->nullable();        // recoverable | non_recoverable
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('purchase_settings');
    }
};
