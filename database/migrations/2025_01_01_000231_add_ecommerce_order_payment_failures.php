<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase F3 - payments confirmed or failed (sections 19.20 and 19.22):
 *
 * - ecommerce_orders.payment_failures: how many times its payment was marked
 *   failed - Phase F4's review points out two or more;
 * - ecommerce_orders.payment_failure_reason: why, the last time.
 *
 * A payment recorded is a customer payment of the order's invoice
 * (sales_payments), so it needs no table of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_failures')->default(0)->after('payment_reference');
            $table->string('payment_failure_reason', 500)->nullable()->after('payment_failures');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_orders', fn (Blueprint $table) => $table->dropColumn(['payment_failures', 'payment_failure_reason']));
    }
};
