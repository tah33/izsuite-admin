<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase D4 - checkout and the order (sections 19.15 to 19.20):
 *
 * - ecommerce_orders: an order placed on a storefront - its number, contact
 *   (and whether it made it), location and currency at its rate, its three
 *   statuses, its totals, the customer's details and addresses as given,
 *   how it is delivered and paid (each a snapshot), the customer's note and
 *   payment reference, where it came from, the consents given, and the
 *   hash of its own link (Phase D5);
 * - ecommerce_order_lines: its products - each a snapshot of what was bought
 *   and at what price, from where, with its tax - and its shipping;
 * - ecommerce_carts.ecommerce_order_id: a converted cart's order;
 * - ecommerce_settings.checkout_phone and checkout_notes: whether checkout
 *   asks for a phone number and a note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained();                              // kept while it has orders
            $table->foreignId('ecommerce_cart_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('contact_id')->constrained();                                 // kept while it has orders
            $table->boolean('contact_created')->default(false);                             // the order made its contact
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');                               // pending | confirmed | on_hold | processing | completed | cancelled
            $table->string('payment_status', 20)->default('unpaid');                        // unpaid | pending | authorised | partially_paid | paid | failed | partially_refunded | refunded
            $table->string('fulfilment_status', 20)->default('unfulfilled');                // not_required | unfulfilled | partially_fulfilled | ready_for_pickup | fulfilled | shipped | delivered | partially_returned | returned
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 20, 10)->default(1);                           // the base currency per unit
            $table->string('price_tax_mode', 10);                                           // as the business kept prices then
            $table->string('tax_rounding', 10);
            $table->boolean('prices_include_tax')->nullable();                              // as the store showed them; null: taxes off
            $table->decimal('subtotal', 15, 2)->default(0);                                 // its products, as calculated
            $table->decimal('shipping_total', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2)->default(0);
            $table->decimal('base_grand_total', 15, 2)->default(0);
            $table->json('taxes')->nullable();
            $table->json('customer');                                                       // name, email, phone, company - as given
            $table->string('customer_email', 255)->index();                                // its e-mail in small letters, to find by
            $table->json('billing_address');
            $table->json('shipping_address')->nullable();
            $table->string('delivery', 10);                                                 // shipping | pickup | none
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pickup_point_id')->nullable()->constrained()->nullOnDelete();
            $table->json('delivery_snapshot')->nullable();
            $table->foreignId('ecommerce_payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payment_snapshot');
            $table->string('payment_reference', 100)->nullable();
            $table->string('customer_note', 1000)->nullable();
            $table->string('source', 20)->default('storefront');
            $table->json('campaign')->nullable();                                           // utm source, medium and campaign, and the referrer
            $table->json('consents');                                                       // terms and privacy accepted, marketing given
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->char('access_token_hash', 64)->unique();                                // its own link's (Phase D5)
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'status']);
            $table->index(['contact_id']);
        });

        Schema::create('ecommerce_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->default('product');                                // product | shipping
            $table->foreignId('catalog_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);                                                   // as the store showed it
            $table->json('options')->nullable();                                            // its variant's, [{name, value}]
            $table->string('sku', 100)->nullable();
            $table->string('name', 255);                                                    // as its invoice will name it
            $table->string('unit', 30)->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);                                           // as the business keeps prices
            $table->string('price_source', 20)->nullable();                                 // item | price_list
            $table->unsignedBigInteger('price_list_id')->nullable();
            $table->unsignedBigInteger('price_list_rule_id')->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->string('tax_name', 100)->nullable();
            $table->string('tax_code', 50)->nullable();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->string('tax_treatment', 30)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shown_unit_price', 15, 2)->nullable();                         // as the store showed it
            $table->decimal('shown_amount', 15, 2)->nullable();
            $table->decimal('weight', 12, 3)->nullable();                                   // kg, the line's
            $table->boolean('shipped')->default(false);                                     // a physical product, to deliver
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('ecommerce_carts', function (Blueprint $table) {
            $table->timestamp('converted_at')->nullable()->after('expires_at');
        });

        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->string('checkout_phone', 10)->default('required')->after('cart_days');   // required | optional | hidden
            $table->boolean('checkout_notes')->default(true)->after('checkout_phone');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn(['checkout_phone', 'checkout_notes']));
        Schema::table('ecommerce_carts', fn (Blueprint $table) => $table->dropColumn('converted_at'));
        Schema::dropIfExists('ecommerce_order_lines');
        Schema::dropIfExists('ecommerce_orders');
    }
};
