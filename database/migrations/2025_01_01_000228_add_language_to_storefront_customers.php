<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase E3 - a storefront customer's language (section 19.16): one of
 * the store's, chosen on their account - none until they choose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_customers', function (Blueprint $table) {
            $table->string('language', 10)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_customers', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
