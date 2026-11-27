<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H2a: automatic discounts, and how discounts go together.
 *
 * - ecommerce_discounts.priority: the order discounts are taken in at
 *   checkout - 1 first - and combines: whether it goes with other discounts.
 *   An automatic discount is one without a code (the column was made
 *   nullable for it in H1).
 * - ecommerce_order_promotions: each discount an order took, as it was - its
 *   name, its code (none when automatic), what it takes off, and how much it
 *   took, as the business keeps prices in the order's currency - in the order
 *   it was taken. Its uses are its orders' rows.
 * - H1's ecommerce_orders.promotion and ecommerce_discount_id move there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_discounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')->default(1)->after('per_customer_limit');
            $table->boolean('combines')->default(false)->after('priority');
        });

        Schema::create('ecommerce_order_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ecommerce_discount_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 40)->nullable();                                         // none when automatic
            $table->string('kind', 20);                                                     // percentage | amount | free_shipping
            $table->decimal('value', 15, 4)->nullable();
            $table->decimal('off', 15, 2);                                                  // what it took, as the business keeps prices
            $table->timestamps();
        });

        // H1's orders: the code each was placed with.
        DB::table('ecommerce_orders')->whereNotNull('promotion')->orderBy('id')->each(function (object $order) {
            $promotion = json_decode($order->promotion, true);

            DB::table('ecommerce_order_promotions')->insert([
                'ecommerce_order_id'    => $order->id,
                'ecommerce_discount_id' => $order->ecommerce_discount_id,
                'name'                  => $promotion['name'],
                'code'                  => $promotion['code'],
                'kind'                  => $promotion['kind'],
                'value'                 => $promotion['value'],
                'off'                   => $promotion['off'],
                'created_at'            => $order->created_at,
                'updated_at'            => $order->created_at,
            ]);
        });

        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ecommerce_discount_id');
            $table->dropColumn('promotion');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->foreignId('ecommerce_discount_id')->nullable()->after('discount_reason')->constrained()->nullOnDelete();
            $table->json('promotion')->nullable()->after('ecommerce_discount_id');
        });

        // Each order's code, as H1 kept it; its automatic discounts are not kept.
        DB::table('ecommerce_order_promotions')->whereNotNull('code')->orderBy('id')->each(function (object $row) {
            DB::table('ecommerce_orders')->where('id', $row->ecommerce_order_id)->update([
                'ecommerce_discount_id' => $row->ecommerce_discount_id,
                'promotion'             => json_encode(['name' => $row->name, 'code' => $row->code, 'kind' => $row->kind, 'value' => $row->value, 'off' => $row->off]),
            ]);
        });

        Schema::dropIfExists('ecommerce_order_promotions');

        Schema::table('ecommerce_discounts', fn (Blueprint $table) => $table->dropColumn(['priority', 'combines']));
    }
};
