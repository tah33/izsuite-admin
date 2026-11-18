<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase G3b: the follow-up warnings' periods (section 17.23), beside
 * the closing-soon one in Settings → CRM - the days a new lead may wait for
 * its first contact (2 to start), and the days without activity after which
 * an opportunity closing soon is warned about (14 to start).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('lead_response_days')->default(2)->after('closing_soon_days');
            $table->unsignedSmallInteger('recent_activity_days')->default(14)->after('lead_response_days');
        });
    }

    public function down(): void
    {
        Schema::table('crm_settings', fn (Blueprint $table) => $table->dropColumn(['lead_response_days', 'recent_activity_days']));
    }
};
