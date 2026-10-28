<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's exchange rates (Doc 11 section 7): for each of its other
 * currencies, what one unit is worth in its base currency, from a date on.
 * Every rate entered is kept - the history, one per currency and day - and a
 * document takes the latest one on or before its own date, which it then keeps
 * as its own (Doc 14 section 14.9).
 *
 * base_currency is the base the rate was entered against. The base can only
 * change before the business has transactions; rates for an earlier one are
 * kept, but no longer used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->char('base_currency', 3);
            $table->decimal('rate', 20, 8);        // base currency per one unit of `currency`
            $table->date('effective_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'base_currency', 'currency', 'effective_date'], 'exchange_rates_one_a_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
