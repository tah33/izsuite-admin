<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 13 Phase D3: the saved card charged without the business there - on a
 * renewal's due day, a trial's last day, and again on grace days. Each try
 * is a payment row - succeeded, failed, needing the cardholder to confirm,
 * or still processing - kept with the business's day it was made, so a day
 * is tried once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->date('attempted_on')->nullable()->after('status');
            $table->index(['subscription_invoice_id', 'attempted_on']);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropIndex(['subscription_invoice_id', 'attempted_on']);
            $table->dropColumn('attempted_on');
        });
    }
};
