<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales Lite's documents (Doc 08 sections 4 to 12): quotations, proforma
 * invoices and invoices, one table for all three - they share their fields,
 * lines and totals. Credit notes join them later.
 *
 * - sales_documents: the header. A draft has no number; the number comes when
 *   an invoice is issued or a quotation or proforma is first sent (Doc 06
 *   section 11). Totals are worked out by SalesCalculator - in the document's
 *   currency, and in the base currency at its exchange rate, for the
 *   Dashboard and reports. business_snapshot and customer_snapshot keep the
 *   business and the customer as they were when it was numbered (Doc 14
 *   section 14.13). source_id is the document it was converted from.
 * - sales_document_lines: an item or a free description each, with the tax as
 *   it was when the line was saved, a combo's components and a physical
 *   product's warranty.
 * - sales_document_revisions: a sent quotation or proforma changed later
 *   keeps each earlier version here, whole (Doc 08 section 12).
 *
 * Contacts, items, tax rates and locations a document uses cannot be deleted
 * (see their services); the nullOnDelete foreign keys only let a business be
 * deleted with everything in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                                  // quotation | proforma | invoice
            $table->string('number', 50)->nullable();
            $table->unsignedSmallInteger('revision')->default(0);
            $table->string('status', 20)->default('draft');
            $table->string('payment_status', 20)->default('not_applicable');
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('document_date');
            $table->date('due_date')->nullable();                        // an invoice's
            $table->date('expiry_date')->nullable();                     // a quotation's or proforma's
            $table->unsignedSmallInteger('payment_terms')->nullable();   // the days the due date came from
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 20, 8)->default(1);         // base currency per unit of `currency`
            $table->string('price_tax_mode', 10)->default('exclusive');
            $table->string('tax_rounding', 10)->default('line');
            $table->string('customer_reference', 100)->nullable();
            $table->string('sales_reference', 100)->nullable();
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
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->json('business_snapshot')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('sales_documents')->nullOnDelete();
            $table->string('status_reason', 500)->nullable();            // why it was rejected, cancelled or voided
            $table->timestamp('issued_at')->nullable();                  // when it was first numbered
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'type', 'number']);
            $table->index(['workspace_id', 'type', 'document_date']);
        });

        Schema::create('sales_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();
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
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->json('components')->nullable();
            $table->json('warranty')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sales_document_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_document_revisions');
        Schema::dropIfExists('sales_document_lines');
        Schema::dropIfExists('sales_documents');
    }
};
