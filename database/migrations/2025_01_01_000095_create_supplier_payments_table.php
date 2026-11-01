<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchases Lite's supplier payments (Doc 09 sections 12 and 13): money paid
 * out of a payment account against a recorded purchase, each numbered as its
 * Supplier Payment Voucher (SPV-00001), and reversed - never edited or
 * deleted - when it was a mistake.
 *
 * `amount` left the account, in its currency; `applied_amount` came off the
 * purchase, in its currency, at `exchange_rate` (account currency per unit of
 * the purchase's). Withholding tax kept back from the supplier also comes off
 * the purchase, but never leaves the account.
 *
 * A payment account's history names the purchase a supplier payment paid,
 * as it names the invoice a customer payment paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50);                                      // its voucher number
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date');
            $table->string('method', 20)->nullable();                         // cash | bank_transfer | card | mobile_money | cheque | other
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('amount', 15, 2);                                 // out of the account, in its currency
            $table->decimal('applied_amount', 15, 2);                         // off the purchase, in its currency
            $table->decimal('exchange_rate', 20, 8)->default(1);              // account currency per unit of the purchase's
            $table->decimal('base_amount', 15, 2);                            // `amount` in the base currency
            $table->foreignId('withholding_tax_id')->nullable()->constrained()->nullOnDelete();
            $table->string('withholding_name', 100)->nullable();
            $table->string('withholding_code', 20)->nullable();
            $table->decimal('withholding_rate', 7, 4)->nullable();
            $table->decimal('withholding_amount', 15, 2)->default(0);         // in the purchase's currency
            $table->decimal('balance_after', 15, 2)->default(0);              // what the purchase had left just after it - for the voucher
            $table->json('template_snapshot')->nullable();
            $table->string('status', 20)->default('completed');               // completed | reversed
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'payment_date']);
        });

        Schema::table('account_transactions', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->after('sales_document_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_id');
        });

        Schema::dropIfExists('supplier_payments');
    }
};
