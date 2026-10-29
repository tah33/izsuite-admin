<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A contact's tax treatment (Doc 03 section 8): standard, zero-rated, exempt,
 * out of scope or no tax - the treatments of Doc 11 section 10. Empty is
 * none chosen. A customer's treatment picks the tax their new documents start
 * with, ahead of the item's and the business's (Doc 11 section 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->string('tax_treatment', 20)->nullable()->after('tax_country');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->dropColumn('tax_treatment');
        });
    }
};
