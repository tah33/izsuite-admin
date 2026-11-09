<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 13 Phase C2a: a subscription's renewals. When a paid period ends
 * unpaid, its grace runs to `grace_ends_on`; a cancellation waits for the
 * period's end; `last_notice_on` keeps the daily e-mails to one a day. An
 * invoice says what it is for: the first payment, a renewal - or, from C2b,
 * an upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_subscriptions', function (Blueprint $table) {
            $table->date('grace_ends_on')->nullable()->after('period_ends_on');
            $table->boolean('cancels_at_period_end')->default(false)->after('grace_ends_on');
            $table->date('last_notice_on')->nullable()->after('cancels_at_period_end');
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->string('kind', 10)->default('new')->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('workspace_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['grace_ends_on', 'cancels_at_period_end', 'last_notice_on']);
        });
    }
};
