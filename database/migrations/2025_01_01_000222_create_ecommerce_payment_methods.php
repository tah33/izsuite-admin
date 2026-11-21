<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase D2 - the storefronts' payment methods without a gateway
 * (section 19.22), in Settings → eCommerce: bank transfer, mobile money or a
 * wallet, cash on delivery and pay on pickup - each into a payment account,
 * with its instructions to the customer, whether the customer gives their
 * payment's reference, and the countries, order amounts and delivery it is
 * offered for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                                                    // its own in the business; customers see it
            $table->string('type', 20);                                                     // bank_transfer | mobile_money | cash_on_delivery | pay_on_pickup
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->string('instructions', 2000)->nullable();                               // how to pay, shown to the customer
            $table->string('reference', 10)->default('none');                               // none | optional | required: the customer's payment reference
            $table->json('countries')->nullable();                                          // none: every country
            $table->decimal('min_order', 15, 2)->nullable();                                // in its account's currency
            $table->decimal('max_order', 15, 2)->nullable();
            $table->string('delivery', 10)->default('any');                                 // any | shipped | pickup
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_payment_methods');
    }
};
