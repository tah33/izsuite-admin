<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase C4: the receipt (sections 18.19 and 18.20). An order counts
 * the copies of its receipt made - the first is the original, every later
 * one says Reprint - and keeps the return policy and footer its receipt
 * ended with, as Settings → Point of Sale had them when it was sold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('receipt_prints')->default(0)->after('cash_rounding');
            $table->json('receipt_texts')->nullable()->after('receipt_prints');
        });
    }

    public function down(): void
    {
        Schema::table('pos_orders', fn (Blueprint $table) => $table->dropColumn(['receipt_prints', 'receipt_texts']));
    }
};
