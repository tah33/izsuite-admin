<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase F6b - a payment link's customer saying they paid (section
 * 19.23):
 *
 * - ecommerce_payment_links.reported_at: when the customer said, on the
 *   link's page, that they had paid what it asks - none while nothing is
 *   reported, or once the business marked it not received;
 * - reported_method: the way they paid, among the link's methods, as it
 *   was then - {id, name, type, payment_account_id} - so their payment is
 *   recorded into its account;
 * - reported_reference: their payment's reference, when they gave one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_payment_links', function (Blueprint $table) {
            $table->timestamp('reported_at')->nullable()->after('sent_to');
            $table->json('reported_method')->nullable()->after('reported_at');
            $table->string('reported_reference', 100)->nullable()->after('reported_method');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_payment_links', fn (Blueprint $table) => $table->dropColumn(['reported_at', 'reported_method', 'reported_reference']));
    }
};
