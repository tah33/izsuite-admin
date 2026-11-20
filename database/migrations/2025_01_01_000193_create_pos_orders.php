<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase C2: payment and the completed sale (sections 18.9, 18.16,
 * 18.18 and 18.20). A POS order is the sale made at a register, in a
 * session, by its cashier: completed, it is one invoice of the business -
 * numbered by the register's own receipt run - paid by its payments, each a
 * customer payment into its method's account. The order keeps what POS
 * knows besides: what was handed over and the change, each payment's method
 * as it was then, and where each line's price came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_session_id')->constrained();                // a session is never deleted
            $table->foreignId('pos_register_id')->constrained();
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('sales_document_id')->nullable()->constrained('sales_documents')->nullOnDelete();   // its invoice
            $table->string('number', 50)->nullable();                         // its receipt number, its invoice's: R1-00001
            $table->string('status', 20)->default('completed');               // completed: an order is a sale once it is (Phase D1's held orders are kept apart)
            $table->char('currency', 3);                                       // the register's
            $table->decimal('exchange_rate', 20, 8)->default(1);              // base per unit of it
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('tendered', 15, 2)->default(0);                   // what the customer handed over, every method's
            $table->decimal('change_given', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['pos_session_id', 'status']);
            $table->index(['workspace_id', 'status', 'completed_at']);
        });

        Schema::create('pos_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method_name', 100);                               // as it was named then
            $table->string('method_type', 20);                                // cash | card | mobile_money | bank_transfer | other
            $table->foreignId('sales_payment_id')->nullable()->constrained('sales_payments')->nullOnDelete();
            $table->decimal('amount', 15, 2);                                 // what it paid of the order
            $table->decimal('tendered', 15, 2);                               // what was handed over: the amount, or more when it gives change
            $table->decimal('change_given', 15, 2)->default(0);
            $table->string('reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('pos_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_line_id')->constrained('sales_document_lines')->cascadeOnDelete();
            $table->string('price_source', 30);                               // item | price_list | customer_price_list
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_order_lines');
        Schema::dropIfExists('pos_order_payments');
        Schema::dropIfExists('pos_orders');
    }
};
