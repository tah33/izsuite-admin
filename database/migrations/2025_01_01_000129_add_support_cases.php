<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase B3: tickets become support cases a business opens from the
 * app (section 15.22), so a case belongs to the business it is about.
 *
 * Each message says whether izSuite staff wrote it: staff write with the
 * admin's editor, and everyone else's words are shown as text, never as
 * markup. Messages so far were staff's where someone other than the
 * ticket's requester wrote them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['workspace_id', 'id']);
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->boolean('by_staff')->default(false)->after('user_id');
        });

        DB::table('ticket_messages')
            ->whereRaw('user_id <> (select tickets.user_id from tickets where tickets.id = ticket_messages.ticket_id)')
            ->update(['by_staff' => true]);
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropColumn('by_staff');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropIndex(['workspace_id', 'id']);
            $table->dropColumn('workspace_id');
        });
    }
};
