<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase D5 - an online order's e-mails to its customer (section
 * 19.35), its history kept with it: each one's kind (D5's is order_received),
 * to whom, and whether it went - queued, sent, or failed after its tries,
 * with why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_order_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);                                                     // order_received
            $table->string('to', 255);
            $table->string('status', 10)->default('queued');                                // queued | sent | failed
            $table->timestamp('sent_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_emails');
    }
};
