<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 13 Phase C2b: a move to a lower plan - or from yearly to monthly -
 * waits for the end of the paid period. The plan and cycle it moves to are
 * kept on the subscription until then; its renewal is for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_subscriptions', function (Blueprint $table) {
            $table->foreignId('scheduled_package_id')->nullable()->after('cancels_at_period_end')->constrained('packages')->nullOnDelete();
            $table->string('scheduled_cycle', 10)->nullable()->after('scheduled_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_package_id');
            $table->dropColumn('scheduled_cycle');
        });
    }
};
