<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase G1 (sections 16.21 and 16.27): a customer's credit control -
 * their credit limit in the base currency (none for no limit), whether they
 * may buy on credit at all, whether going over warns or blocks, and a note
 * shown when a sale is made for them; whether confirmed orders not invoiced
 * yet count toward a customer's exposure (off to start); and why a sales
 * document went over, when someone allowed to took it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->decimal('credit_limit', 15, 2)->nullable()->after('customer_payment_terms');
            $table->boolean('credit_sales')->default(true)->after('credit_limit');
            $table->string('credit_action', 10)->default('warn')->after('credit_sales');
            $table->string('credit_warning', 500)->nullable()->after('credit_action');
        });

        Schema::table('sales_settings', function (Blueprint $table) {
            $table->boolean('credit_counts_orders')->default(false)->after('customer_advances');
        });

        Schema::table('sales_documents', function (Blueprint $table) {
            $table->string('credit_reason', 500)->nullable()->after('discount_reason');
        });
    }

    public function down(): void
    {
        Schema::table('sales_documents', fn (Blueprint $table) => $table->dropColumn('credit_reason'));
        Schema::table('sales_settings', fn (Blueprint $table) => $table->dropColumn('credit_counts_orders'));
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn(['credit_limit', 'credit_sales', 'credit_action', 'credit_warning']));
    }
};
