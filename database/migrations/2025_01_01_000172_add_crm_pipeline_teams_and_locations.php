<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase D3b (section 17.13): several pipelines, each for chosen sales
 * teams or locations. Empty, a pipeline is for all of them, as every pipeline
 * so far is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_pipelines', function (Blueprint $table) {
            $table->json('team_ids')->nullable()->after('is_active');
            $table->json('location_ids')->nullable()->after('team_ids');
        });
    }

    public function down(): void
    {
        Schema::table('crm_pipelines', fn (Blueprint $table) => $table->dropColumn(['team_ids', 'location_ids']));
    }
};
