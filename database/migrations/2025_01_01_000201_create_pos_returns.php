<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase E1: returns and their refunds (sections 18.23 and 18.24). A
 * POS return is made from the original sale - its receipt - numbered
 * RT-00001, at the register of the cashier's open session: the items and
 * quantities brought back, each with its condition, and why. Underneath it is
 * the Sales app's credit note of the sale's invoice, at its prices,
 * discounts and taxes - so it counts once - and its refund: paid out in cash,
 * to the sale's own payment method, or left as the customer's credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);                                        // RT-00001
            $table->foreignId('pos_order_id')->constrained();                    // the sale it returns from
            $table->foreignId('pos_session_id')->constrained();                  // the session it was made in
            $table->foreignId('pos_register_id')->constrained();
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('credit_note_id')->constrained('sales_documents');  // its credit note, issued
            $table->string('reason', 500);
            $table->text('notes')->nullable();
            $table->char('currency', 3);
            $table->decimal('total', 15, 2);                                     // what it credits
            $table->string('refund_method', 20);                                 // cash | original | credit
            $table->string('refund_method_name', 100)->nullable();               // the payment method as it was named then
            $table->string('refund_method_type', 20)->nullable();                // its type: cash for the drawer
            $table->foreignId('refund_payment_id')->nullable()->constrained('sales_payments')->nullOnDelete();
            $table->decimal('refunded', 15, 2)->default(0);                      // paid out; the rest is the customer's credit
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['pos_session_id']);
            $table->index(['pos_order_id']);
        });

        Schema::create('pos_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_line_id')->constrained('sales_document_lines');   // the sale's line
            $table->decimal('quantity', 15, 3);
            $table->string('condition', 20);                                     // resellable | damaged | quarantined | not_returned
            $table->decimal('amount', 15, 2);                                    // what it credits, before the sale's discount and tax
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_return_lines');
        Schema::dropIfExists('pos_returns');
    }
};
