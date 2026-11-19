<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase B2a: a supervisor's side of POS sessions (sections 18.6 and
 * 18.27). Settings → Point of Sale gains the variance limit: a closing whose
 * variance goes beyond it waits for someone who approves variances. A Z
 * report keeps why a supervisor closed someone else's session, and whether
 * its variance needed approval; it is marked once when approved, and once
 * when its session is reopened - its figures never change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->decimal('variance_limit', 15, 2)->nullable()->after('receipt_footer');   // in each session's currency; null for no approval
        });

        Schema::table('pos_session_closings', function (Blueprint $table) {
            $table->string('closer_reason', 500)->nullable()->after('notes');                // a supervisor closing someone else's session
            $table->boolean('needs_approval')->default(false)->after('closer_reason');        // its variance went beyond the limit
            $table->foreignId('approved_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('reopened_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            $table->string('reopen_reason', 500)->nullable()->after('reopened_at');
        });
    }

    public function down(): void
    {
        Schema::table('pos_session_closings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['closer_reason', 'needs_approval', 'approved_at', 'reopened_at', 'reopen_reason']);
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn('variance_limit');
        });
    }
};
