<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase I3: the devices registered at a POS register (section 18.30)
 * - receipt printers, barcode scanners, cash drawers and customer displays -
 * each with how and where it is connected, whether it is in use, and when it
 * was last used and last tested.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_register_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);                         // receipt_printer | barcode_scanner | cash_drawer | customer_display
            $table->string('name', 100);
            $table->string('connection', 20);                   // browser | usb | network | bluetooth
            $table->string('address')->nullable();              // an IP address, a port, a device's own name
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
            $table->index(['pos_register_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_devices');
    }
};
