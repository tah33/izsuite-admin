<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase E2 - a customer account's orders and addresses (sections
 * 19.16 and 19.17):
 *
 * - ecommerce_orders.storefront_customer_id: the account an order is - one
 *   placed signed in, or its e-mail's guest order once the account is
 *   verified;
 * - storefront_customer_addresses: the account's saved addresses, each as
 *   checkout takes one, with a default for billing and one for delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->foreignId('storefront_customer_id')->nullable()->after('contact_created')->constrained()->nullOnDelete();
        });

        Schema::create('storefront_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_customer_id')->constrained()->cascadeOnDelete();
            $table->string('line1', 200);
            $table->string('line2', 200)->nullable();
            $table->string('city', 100);
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country', 2);
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_customer_addresses');

        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_customer_id');
        });
    }
};
