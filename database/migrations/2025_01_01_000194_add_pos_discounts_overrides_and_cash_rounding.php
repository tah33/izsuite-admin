<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase C3: discounts, price overrides and cash rounding (sections
 * 18.12 to 18.14 and 18.32). Settings → Point of Sale gains the discount
 * limit - the largest discount, as a percentage, given without Discount
 * Beyond the Limit - and the cash rounding step. An order keeps why its
 * discounts went beyond the limit and who allowed it, and its cash
 * rounding; each line the price an override replaced, and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->decimal('discount_limit', 5, 2)->nullable()->after('denominations');     // a percentage; null for none
            $table->decimal('cash_rounding', 15, 2)->nullable()->after('discount_limit');    // the step a cash sale's total rounds to; null for none
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->string('discount_reason', 500)->nullable()->after('notes');              // why its discounts went beyond the limit
            $table->foreignId('discount_approved_by')->nullable()->after('discount_reason')->constrained('users')->nullOnDelete();
            $table->decimal('cash_rounding', 15, 2)->default(0)->after('change_given');     // added to its total, or taken off it
        });

        Schema::table('pos_order_lines', function (Blueprint $table) {
            $table->decimal('standard_price', 15, 2)->nullable()->after('price_source');    // the price an override replaced
            $table->string('override_reason', 500)->nullable()->after('standard_price');
        });
    }

    public function down(): void
    {
        Schema::table('pos_order_lines', fn (Blueprint $table) => $table->dropColumn(['standard_price', 'override_reason']));

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_approved_by');
            $table->dropColumn(['discount_reason', 'cash_rounding']);
        });

        Schema::table('pos_settings', fn (Blueprint $table) => $table->dropColumn(['discount_limit', 'cash_rounding']));
    }
};
