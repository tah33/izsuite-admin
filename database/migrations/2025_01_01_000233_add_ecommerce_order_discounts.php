<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase F5b - an online order's discount, given before payment by
 * whoever may edit orders (section 19.21):
 *
 * - ecommerce_orders.discount_type: percent or amount - none without one;
 * - ecommerce_orders.discount_value: the percentage, or the amount in the
 *   order's currency;
 * - ecommerce_orders.discount_reason: why it was given, for the business
 *   alone.
 *
 * What it takes off is the order's discount_total, worked out with its
 * products (EcommerceOrderTotals).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->string('discount_type', 10)->nullable()->after('shipping_total');
            $table->decimal('discount_value', 15, 4)->nullable()->after('discount_type');
            $table->string('discount_reason', 500)->nullable()->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_orders', fn (Blueprint $table) => $table->dropColumn(['discount_type', 'discount_value', 'discount_reason']));
    }
};
