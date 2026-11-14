<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase D2 (section 16.14): deposit invoices. A deposit is an invoice
 * of its sales order (source_id) marked is_deposit; the order's later
 * invoices deduct it with a negative line naming it (deposit_invoice_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->boolean('is_deposit')->default(false)->after('over_invoice_reason');
        });

        Schema::table('sales_document_lines', function (Blueprint $table) {
            // A deduction of that deposit invoice, on a later invoice of the same order.
            $table->foreignId('deposit_invoice_id')->nullable()->after('source_line_id')->constrained('sales_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_document_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deposit_invoice_id');
        });

        Schema::table('sales_documents', fn (Blueprint $table) => $table->dropColumn('is_deposit'));
    }
};
