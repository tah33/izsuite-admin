<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase D1 (sections 16.15, 16.16 and 16.18): customer advances -
 * money a customer paid ahead, or paid over an invoice - and the allocations
 * that apply an advance, or a credit note's remaining credit, to the
 * customer's issued invoices. An allocation taken off stays on record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50);
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_document_id')->nullable()->constrained()->nullOnDelete();   // the order it was paid for, or the invoice it was paid over
            $table->foreignId('sales_payment_id')->nullable()->constrained()->nullOnDelete();    // the payment whose extra it kept
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date');
            $table->string('method', 20)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('currency', 3);                                          // the advance's - what it settles invoices in
            $table->decimal('amount', 15, 2);                                       // in its currency
            $table->decimal('account_amount', 15, 2);                               // into the account, in the account's currency
            $table->decimal('exchange_rate', 20, 8)->default(1);                    // account currency per unit of the advance's
            $table->decimal('base_amount', 15, 2)->nullable();
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2);                                      // still to apply
            $table->string('status', 20)->default('completed');                    // completed | reversed
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'contact_id', 'status'], 'customer_advances_customer_index');
        });

        Schema::create('sales_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('sales_documents')->cascadeOnDelete();
            $table->foreignId('customer_advance_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('credit_note_id')->nullable()->constrained('sales_documents')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);                                       // in the invoice's currency, which is its source's
            $table->date('allocation_date');
            $table->string('status', 20)->default('active');                       // active | removed
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('removal_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'status'], 'sales_allocations_invoice_index');
            $table->index(['customer_advance_id', 'status'], 'sales_allocations_advance_index');
            $table->index(['credit_note_id', 'status'], 'sales_allocations_note_index');
        });

        Schema::table('sales_documents', function (Blueprint $table) {
            // An invoice's: what advances and other invoices' credit notes took off it. A credit note's: what it applied to other invoices.
            $table->decimal('allocated_amount', 15, 2)->default(0)->after('refunded_amount');
        });

        Schema::table('sales_settings', function (Blueprint $table) {
            // Section 16.15's "where enabled": recording advances, and keeping an overpayment as one.
            $table->boolean('customer_advances')->default(true)->after('invoice_policy');
        });
    }

    public function down(): void
    {
        Schema::table('sales_settings', fn (Blueprint $table) => $table->dropColumn('customer_advances'));
        Schema::table('sales_documents', fn (Blueprint $table) => $table->dropColumn('allocated_amount'));
        Schema::dropIfExists('sales_allocations');
        Schema::dropIfExists('customer_advances');
    }
};
