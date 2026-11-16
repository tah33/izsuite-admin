<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase D3a: a stage's stale-after period - the days an open
 * opportunity can be at it before it is stale - and the opportunity's
 * details it needs filled in before one enters it (section 17.13). Both
 * start empty: no stage is stale, none needs anything more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_stages', function (Blueprint $table) {
            $table->unsignedSmallInteger('stale_after_days')->nullable()->after('category');
            $table->json('required_fields')->nullable()->after('stale_after_days');
        });
    }

    public function down(): void
    {
        Schema::table('crm_stages', fn (Blueprint $table) => $table->dropColumn(['stale_after_days', 'required_fields']));
    }
};
