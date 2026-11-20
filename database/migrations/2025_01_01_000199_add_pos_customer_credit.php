<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase D3b: the customer's credit as a payment (section 18.16). A
 * POS sale can be paid in part by the customer's own credit - their
 * advances and credit notes with credit left - applied to its invoice: the
 * order keeps how much.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->decimal('credit_applied', 15, 2)->default(0)->after('on_account');
        });
    }

    public function down(): void
    {
        Schema::table('pos_orders', fn (Blueprint $table) => $table->dropColumn('credit_applied'));
    }
};
