<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase G1 - an online order's shipments and pickups (section 19.27):
 *
 * - ecommerce_fulfilments: a shipment of an order's products - shipped, then
 *   delivered, or cancelled while not delivered - or its readiness for pickup
 *   - ready, then collected, or cancelled while not collected; numbered 1, 2…
 *   within its order; from its location (a warehouse waits for Inventory), on
 *   a day, with its carrier, tracking number and link, the business's notes,
 *   the day it arrived or was collected, and why it was cancelled;
 * - ecommerce_fulfilment_lines: how much of each of the order's products it
 *   holds - part of a line is a partial fulfilment.
 *
 * No stock moves, and a fulfilment is never a sale of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_fulfilments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ecommerce_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');                                       // 1, 2… within its order
            $table->string('kind', 10);                                                     // shipment | pickup
            $table->string('status', 20);                                                   // shipped | delivered | ready | collected | cancelled
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('fulfilled_on');                                                   // shipped, or made ready
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->text('notes')->nullable();                                              // the business's alone
            $table->date('completed_on')->nullable();                                       // delivered, or collected
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ecommerce_order_id', 'sequence']);
        });

        Schema::create('ecommerce_fulfilment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_fulfilment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ecommerce_order_line_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_fulfilment_lines');
        Schema::dropIfExists('ecommerce_fulfilments');
    }
};
