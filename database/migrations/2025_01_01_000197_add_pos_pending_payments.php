<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase D2: pending payments (section 18.16). A payment method can
 * wait to be confirmed - a card or mobile payment the cashier cannot see
 * arrive. Its payment of a sale is pending: it keeps the account its money
 * goes into, and becomes the customer payment once someone who may confirms
 * it - or is declined, why kept, the invoice still owing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_payment_methods', function (Blueprint $table) {
            $table->boolean('confirms_later')->default(false)->after('gives_change');
        });

        Schema::table('pos_order_payments', function (Blueprint $table) {
            $table->foreignId('payment_account_id')->nullable()->after('method_type')->constrained('payment_accounts')->nullOnDelete();
            $table->string('status', 20)->default('completed')->after('reference');           // completed | pending | declined
            $table->foreignId('closed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();   // who confirmed or declined it
            $table->timestamp('closed_at')->nullable()->after('closed_by');
            $table->string('decline_reason', 500)->nullable()->after('closed_at');
        });

        // Each payment so far is its customer payment's: into that one's account.
        DB::table('pos_order_payments')->whereNotNull('sales_payment_id')->update([
            'payment_account_id' => DB::raw('(SELECT payment_account_id FROM sales_payments WHERE sales_payments.id = pos_order_payments.sales_payment_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('pos_order_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_account_id');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['status', 'closed_at', 'decline_reason']);
        });

        Schema::table('pos_payment_methods', fn (Blueprint $table) => $table->dropColumn('confirms_later'));
    }
};
