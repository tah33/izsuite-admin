<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase D1 - shipping and pickup (section 19.26), in Settings →
 * eCommerce:
 *
 * - shipping_zones: where the business ships - its countries, narrowed if
 *   wished to regions, cities and postal codes (a code, or its start with
 *   "*") - in an order: an address takes the first zone it matches;
 * - shipping_methods: a zone's ways of shipping - flat rate, free, by
 *   weight, by order value or by quantity (each in bands), local delivery,
 *   or quoted after the order - each with its price in the base currency,
 *   its tax, its delivery days, the order values and weights it takes, the
 *   categories it carries, and whether it is on;
 * - pickup_points: the business's locations its customers may collect from,
 *   each with its instructions and when an order is ready.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                                                    // its own in the business
            $table->json('countries');                                                      // country codes, one at least
            $table->json('regions')->nullable();                                            // none: every region
            $table->json('cities')->nullable();                                             // none: every city
            $table->json('postal_codes')->nullable();                                       // codes, or starts ending "*"; none: every code
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                                                    // its own in its zone
            $table->string('kind', 20);                                                     // flat | free | weight | order_value | quantity | local_delivery | quotation
            $table->decimal('price', 15, 2)->nullable();                                    // a flat rate's or local delivery's, in the base currency
            $table->json('rates')->nullable();                                              // by weight, order value or quantity: [{from, price}], from lowest
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->unsignedSmallInteger('days_min')->nullable();                           // its delivery days
            $table->unsignedSmallInteger('days_max')->nullable();
            $table->decimal('min_order', 15, 2)->nullable();                                // the order values it takes, in the base currency
            $table->decimal('max_order', 15, 2)->nullable();
            $table->decimal('min_weight', 12, 3)->nullable();                               // the weights it takes, in kg
            $table->decimal('max_weight', 12, 3)->nullable();
            $table->json('category_ids')->nullable();                                       // what it carries; none: every product
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'name']);
        });

        Schema::create('pickup_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('instructions', 1000)->nullable();
            $table->string('ready_within', 100)->nullable();                                // e.g. "Ready in 2 hours"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_points');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
    }
};
