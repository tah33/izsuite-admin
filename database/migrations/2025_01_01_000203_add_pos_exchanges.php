<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase E3: exchanges (section 18.25). A return made as an exchange
 * keeps its credit for the sale of the replacement: that sale is paid by it
 * first - applied from the return's credit note - and the two stay separate,
 * linked records. The sale keeps how much the exchange paid, and what it paid
 * back of the difference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->foreignId('exchange_return_id')->nullable()->after('credit_applied')->constrained('pos_returns')->nullOnDelete();
            $table->decimal('exchange_credit', 15, 2)->default(0)->after('exchange_return_id');    // what the exchange paid of it
            $table->decimal('exchange_refunded', 15, 2)->default(0)->after('exchange_credit');     // the difference paid back in cash
        });

        Schema::table('pos_returns', function (Blueprint $table) {
            $table->foreignId('exchange_order_id')->nullable()->after('refund_decline_reason')->constrained('pos_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_returns', fn (Blueprint $table) => $table->dropConstrainedForeignId('exchange_order_id'));

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exchange_return_id');
            $table->dropColumn(['exchange_credit', 'exchange_refunded']);
        });
    }
};
