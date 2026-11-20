<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase I2: a manager's approval at the cashier's register (section
 * 18.31). A price typed over keeps who approved it, when the cashier may not
 * change prices; a return paid back by another method, who approved that.
 * A discount beyond the limit already names its approver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_order_lines', function (Blueprint $table) {
            $table->foreignId('override_approved_by')->nullable()->after('override_reason')->constrained('users')->nullOnDelete();
        });

        Schema::table('pos_returns', function (Blueprint $table) {
            $table->foreignId('refund_approved_by')->nullable()->after('refund_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_returns', fn (Blueprint $table) => $table->dropConstrainedForeignId('refund_approved_by'));
        Schema::table('pos_order_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('override_approved_by'));
    }
};
