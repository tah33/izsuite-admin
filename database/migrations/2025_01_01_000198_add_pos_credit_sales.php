<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase D3a: credit sales (section 18.17). A POS sale to a named
 * customer can leave what its payments do not pay on their account: the
 * order keeps how much, and why it went over their credit limit when someone
 * who may override it let it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->decimal('on_account', 15, 2)->default(0)->after('change_given');
            $table->string('credit_reason', 500)->nullable()->after('on_account');
        });
    }

    public function down(): void
    {
        Schema::table('pos_orders', fn (Blueprint $table) => $table->dropColumn(['on_account', 'credit_reason']));
    }
};
