<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase F2: a paid out - an expense paid from a POS session's drawer
 * (section 18.26), with the Expenses app. The cash movement keeps who it is
 * paid to and its expense category, so one waiting for approval can become
 * its expense once approved; and the expense it became.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_cash_movements', function (Blueprint $table) {
            $table->string('payee_name')->nullable()->after('payment_account_id');
            $table->foreignId('expense_category_id')->nullable()->after('payee_name')->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->after('account_transfer_id')->constrained('expenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
            $table->dropConstrainedForeignId('expense_category_id');
            $table->dropColumn('payee_name');
        });
    }
};
