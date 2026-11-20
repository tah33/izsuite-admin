<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase E2: the refund rules (section 18.24). A return's refund can be
 * paid back by another of the register's methods - why kept - and, by a
 * method that waits to be confirmed, waits as pending: what it will pay out
 * of which account, until someone who may confirms it - or declines it, why
 * kept, the credit staying on the credit note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_returns', function (Blueprint $table) {
            $table->string('refund_status', 20)->default('completed')->after('refunded');   // completed | pending | declined
            $table->string('refund_reason', 500)->nullable()->after('refund_status');      // why another method paid it back
            $table->foreignId('refund_account_id')->nullable()->after('refund_reason')->constrained('payment_accounts')->nullOnDelete();
            $table->decimal('refund_due', 15, 2)->default(0)->after('refund_account_id');  // waiting to be paid out
            $table->foreignId('refund_closed_by')->nullable()->after('refund_due')->constrained('users')->nullOnDelete();
            $table->timestamp('refund_closed_at')->nullable()->after('refund_closed_by');
            $table->string('refund_decline_reason', 500)->nullable()->after('refund_closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('pos_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_account_id');
            $table->dropConstrainedForeignId('refund_closed_by');
            $table->dropColumn(['refund_status', 'refund_reason', 'refund_due', 'refund_closed_at', 'refund_decline_reason']);
        });
    }
};
