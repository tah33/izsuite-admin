<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The First-Time Business Setup (Doc 11 section 3): how far a new business
 * has got through its setup wizard, and when it finished.
 *
 * Every business made before the wizard was set up through Settings, so each
 * counts as set up already - from when it was made. The sidebar says whether
 * the business is set up, so the cached ones are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('setup_step', 20)->nullable()->after('rate_precision');
            $table->timestamp('setup_completed_at')->nullable()->after('setup_step');
        });

        DB::table('workspaces')->update(['setup_completed_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);

        CatalogVersion::bump();
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['setup_step', 'setup_completed_at']);
        });
    }
};
