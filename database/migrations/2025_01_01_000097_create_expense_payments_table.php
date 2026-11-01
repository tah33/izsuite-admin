<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense payments (Doc 10 sections 12 and 13): what the business paid its
 * payees against their recorded expenses, each with its Expense Payment
 * Voucher number (EPV-00001). `amount` came out of the payment account in its
 * own currency; `applied_amount` is what it paid off the expense, in the
 * expense's; base_amount is `amount` in the business's base currency. Tax
 * withheld from the payee settles the expense without leaving the account. A
 * payment is reversed - never edited or deleted - and stays on record.
 * contact_id is the expense's payee contact, when it has one.
 *
 * account_transactions gains expense_id: the expense an expense payment
 * paid, for the account's history and its reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50);                                      // its voucher number
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date');
            $table->string('method', 20)->nullable();                         // cash | bank_transfer | card | mobile_money | cheque | other
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('amount', 15, 2);                                 // out of the account, in its currency
            $table->decimal('applied_amount', 15, 2);                         // off the expense, in its currency
            $table->decimal('exchange_rate', 20, 8)->default(1);              // account currency per unit of the expense's
            $table->decimal('base_amount', 15, 2);                            // `amount` in the base currency
            $table->foreignId('withholding_tax_id')->nullable()->constrained()->nullOnDelete();
            $table->string('withholding_name', 100)->nullable();
            $table->string('withholding_code', 20)->nullable();
            $table->decimal('withholding_rate', 7, 4)->nullable();
            $table->decimal('withholding_amount', 15, 2)->default(0);         // in the expense's currency
            $table->decimal('balance_after', 15, 2)->default(0);              // what the expense had left just after it - for the voucher
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
            $table->foreignId('expense_id')->nullable()->after('purchase_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });

        Schema::dropIfExists('expense_payments');
    }
};
