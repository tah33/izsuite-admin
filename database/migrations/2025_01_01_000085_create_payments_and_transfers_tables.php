<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money moving through a business's payment accounts (Doc 05 sections 10 to
 * 14, Doc 08 sections 13, 14 and 16):
 *
 * - payment_accounts.opening_balance_date: when the opening balance was the
 *   account's balance - required with an opening balance (section 5).
 *   Accounts made before it existed take the day they were made.
 * - sales_payments: a customer's payment against an issued invoice (kind
 *   "payment", numbered as a receipt: RCT-00001) or a refund paid out of a
 *   credit note (kind "refund"). `amount` is what went in or out of the
 *   account, in its currency; `applied_amount` what came off the document, in
 *   the document's currency, at `exchange_rate`. A payment may also carry the
 *   withholding tax the customer kept back, which settles the invoice without
 *   reaching the account. A mistake is reversed, never deleted.
 * - account_transfers: money moved from one of the business's accounts to
 *   another, possibly in another currency. A completed one is voided, not
 *   changed.
 * - account_transactions: each account's history, one row per money in or
 *   out, written by the payment, refund or transfer it records (`source`).
 *   Only completed rows count towards the recorded balance; a reversed or
 *   void one stays in the history. The running balance is worked out as the
 *   history is read, so a backdated row simply takes its place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $t) {
            $t->date('opening_balance_date')->nullable()->after('opening_balance');
        });

        DB::table('payment_accounts')->where('opening_balance', '!=', 0)->whereNull('opening_balance_date')
            ->update(['opening_balance_date' => DB::raw('DATE(created_at)')]);

        Schema::create('sales_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                                       // payment | refund
            $table->string('number', 50)->nullable();                         // a payment's receipt number
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date');
            $table->string('method', 20)->nullable();                         // cash | bank_transfer | card | mobile_money | cheque | other
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('amount', 15, 2);                                 // into or out of the account, in its currency
            $table->decimal('applied_amount', 15, 2);                         // off the document, in its currency
            $table->decimal('exchange_rate', 20, 8)->default(1);              // account currency per unit of the document's
            $table->decimal('base_amount', 15, 2);                            // `amount` in the base currency
            $table->foreignId('withholding_tax_id')->nullable()->constrained()->nullOnDelete();
            $table->string('withholding_name', 100)->nullable();
            $table->string('withholding_code', 20)->nullable();
            $table->decimal('withholding_rate', 7, 4)->nullable();
            $table->decimal('withholding_amount', 15, 2)->default(0);         // in the document's currency
            $table->decimal('balance_after', 15, 2)->default(0);              // what the document had left just after it - for the receipt
            $table->string('status', 20)->default('completed');               // completed | reversed
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'kind', 'number']);
            $table->index(['workspace_id', 'kind', 'payment_date']);
        });

        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_account_id')->constrained('payment_accounts')->cascadeOnDelete();
            $table->foreignId('to_account_id')->constrained('payment_accounts')->cascadeOnDelete();
            $table->date('transfer_date');
            $table->decimal('amount', 15, 2);                                 // sent, in the From account's currency
            $table->decimal('amount_received', 15, 2);                        // received, in the To account's currency
            $table->decimal('exchange_rate', 20, 8)->default(1);              // To currency per unit of the From's
            $table->decimal('base_amount', 15, 2);
            $table->string('reference', 100);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');                   // draft | completed | void
            $table->string('void_reason', 500)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'transfer_date']);
        });

        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('type', 30);                                       // customer_payment | customer_refund | transfer_in | transfer_out
            $table->string('direction', 3);                                   // in | out
            $table->decimal('amount', 15, 2);                                 // always positive, in the account's currency
            $table->decimal('base_amount', 15, 2);
            $table->string('status', 20);                                     // completed | reversed | void | draft
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_document_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('source');
            $table->string('reference', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Named: the generated name is one character over MySQL's 64.
            $table->index(['payment_account_id', 'transaction_date', 'id'], 'account_transactions_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('account_transfers');
        Schema::dropIfExists('sales_payments');

        Schema::table('payment_accounts', function (Blueprint $t) {
            $t->dropColumn('opening_balance_date');
        });
    }
};
