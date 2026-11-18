<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase F2: whether confirming a sales order made from an opportunity
 * marks the opportunity won - Settings → CRM, on to start.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_settings', function (Blueprint $table) {
            $table->boolean('win_on_order')->default(true)->after('qualification_required');
        });
    }

    public function down(): void
    {
        Schema::table('crm_settings', function (Blueprint $table) {
            $table->dropColumn('win_on_order');
        });
    }
};
