<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase F4 - holding, releasing and cancelling an online order
 * (sections 19.21 and 19.24):
 *
 * - ecommerce_orders.held_status: the status an order on hold goes back to
 *   when it is released (pending or confirmed);
 * - ecommerce_orders.status_reason: why it is on hold, or was cancelled;
 * - ecommerce_settings.review_above: Review Orders Above - a total, in the
 *   base currency, over which an order may want a look (none to start);
 * - ecommerce_settings.hold_for_review: whether an order that may want a
 *   look is put on hold as it is placed (off to start).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->string('held_status', 20)->nullable()->after('status');
            $table->string('status_reason', 500)->nullable()->after('held_status');
        });

        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->decimal('review_above', 15, 2)->nullable()->after('checkout_notes');
            $table->boolean('hold_for_review')->default(false)->after('review_above');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn(['review_above', 'hold_for_review']));
        Schema::table('ecommerce_orders', fn (Blueprint $table) => $table->dropColumn(['held_status', 'status_reason']));
    }
};
