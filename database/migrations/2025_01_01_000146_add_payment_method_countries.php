<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase F3b (sections 15.15 and 15.19): the billing countries each of
 * izSuite's ways to pay is offered in - none, every country, as each is
 * today. A business billed elsewhere is not offered it: no card payments, or
 * no instructions for that method on its invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->json('countries')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('countries');
        });
    }
};
