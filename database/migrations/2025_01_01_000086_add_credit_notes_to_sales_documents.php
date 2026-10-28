<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit notes (Doc 08 section 15, Doc 06 section 12) join the other sales
 * documents in sales_documents, as type "credit_note": a credit note is
 * numbered, keeps its customer as they were, and has lines, tax and totals
 * like an invoice. Its source_id is the invoice it credits, and each of its
 * lines points at the invoice line it credits (source_line_id).
 *
 * What has been paid, withheld, credited and refunded is kept on the
 * documents as well, worked out again whenever one of those changes (see
 * InvoiceSettlement):
 *
 * - an invoice: amount_paid (payments, in cash), withholding_amount,
 *   credited_amount (its issued credit notes), refunded_amount (refunds paid
 *   out of those) and balance_due;
 * - a credit note: applied_amount (what came off its invoice),
 *   refunded_amount, and balance_due - the credit still available, which in
 *   Sales Lite can only be refunded.
 *
 * reason is why a credit note was made; status_reason stays why a document
 * was rejected, cancelled or voided.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_documents', function (Blueprint $t) {
            $t->decimal('withholding_amount', 15, 2)->default(0)->after('amount_paid');
            $t->decimal('credited_amount', 15, 2)->default(0)->after('withholding_amount');
            $t->decimal('applied_amount', 15, 2)->default(0)->after('credited_amount');
            $t->decimal('refunded_amount', 15, 2)->default(0)->after('applied_amount');
            $t->string('reason', 500)->nullable()->after('status_reason');
        });

        Schema::table('sales_document_lines', function (Blueprint $t) {
            $t->foreignId('source_line_id')->nullable()->after('sales_document_id')->constrained('sales_document_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_document_lines', function (Blueprint $t) {
            $t->dropConstrainedForeignId('source_line_id');
        });

        Schema::table('sales_documents', function (Blueprint $t) {
            $t->dropColumn(['withholding_amount', 'credited_amount', 'applied_amount', 'refunded_amount', 'reason']);
        });
    }
};
