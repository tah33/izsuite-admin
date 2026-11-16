<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase D2a: how an opportunity ended (section 17.16) - the day it
 * was won or lost and who said so, the won reason, or the lost reason,
 * competitor, notes and a possible follow-up day - and archiving one
 * (section 17.15). Reopening clears the ending; the Activity Log keeps it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->date('closed_on')->nullable()->after('stage_entered_at');                  // won or lost on
            $table->foreignId('closed_by')->nullable()->after('closed_on')->constrained('users')->nullOnDelete();
            $table->string('won_reason')->nullable()->after('closed_by');
            $table->foreignId('lost_reason_id')->nullable()->after('won_reason')->constrained('crm_options')->nullOnDelete();
            $table->string('lost_competitor', 100)->nullable()->after('lost_reason_id');
            $table->text('lost_notes')->nullable()->after('lost_competitor');
            $table->date('follow_up_on')->nullable()->after('lost_notes');
            $table->timestamp('archived_at')->nullable()->after('follow_up_on');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archived_at');
            $table->dropColumn(['follow_up_on', 'lost_notes', 'lost_competitor']);
            $table->dropConstrainedForeignId('lost_reason_id');
            $table->dropColumn('won_reason');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('closed_on');
        });
    }
};
