<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase E2: an activity's reminder - how long before it is due its
 * assignee is reminded, in the bell and by e-mail, the moment that falls
 * (UTC) and when it was sent. One planned before this has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->unsignedSmallInteger('remind_minutes')->nullable()->after('due_at');   // before it is due; none: no reminder
            $table->dateTime('remind_at')->nullable()->after('remind_minutes');            // UTC
            $table->dateTime('reminded_at')->nullable()->after('remind_at');

            $table->index(['status', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropIndex(['status', 'remind_at']);
            $table->dropColumn(['remind_minutes', 'remind_at', 'reminded_at']);
        });
    }
};
