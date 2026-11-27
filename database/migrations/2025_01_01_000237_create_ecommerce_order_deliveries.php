<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase G3a - an online order's digital products delivered and its
 * services performed (sections 19.28 and 19.29): one row for each of the
 * order's products that do not ship, made when it is delivered or performed -
 * or, for a license key or a delivery by hand, when the order is paid and it
 * waits for the business:
 *
 * - a digital product's delivery: how its item delivers it (download,
 *   external link, license key or by hand), waiting, delivered or revoked -
 *   with its license keys (encrypted), the note to the customer, when and by
 *   whom it was delivered, and when, by whom and why it was revoked;
 * - a service: performed on a day, by whom, with the note to the customer.
 *
 * Nothing is a stock movement or a sale of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ecommerce_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ecommerce_order_line_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                                                     // digital | service
            $table->string('method', 20)->nullable();                                       // download | external_link | license_key | manual
            $table->string('status', 20);                                                   // waiting | delivered | revoked | performed
            $table->text('license_keys')->nullable();                                       // encrypted
            $table->text('note')->nullable();                                               // to the customer
            $table->timestamp('delivered_at')->nullable();
            $table->date('performed_on')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 500)->nullable();                               // the business's alone
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_deliveries');
    }
};
