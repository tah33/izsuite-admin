<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A workspace's staff member is now one of the account's own staff - a row of
 * user_staff - not a staff-role account in users.
 *
 * Every staff_id already stored names a users row, which means nothing in
 * user_staff: the same number would point at somebody else. So they are cleared
 * before the new foreign key goes on, and those workspaces simply have no staff
 * member until one of the account's own is picked. The workspaces themselves,
 * and their apps, are not touched.
 *
 * The column keeps its rule: nulled when what it points at is deleted - a
 * workspace should outlive a staff member, not vanish with them, and not block
 * the delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->pointStaffAt('user_staff');
    }

    public function down(): void
    {
        $this->pointStaffAt('users');
    }

    /**
     * Either way the ids stored mean something else afterwards, so none is kept.
     */
    private function pointStaffAt(string $references): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });

        DB::table('workspaces')->update(['staff_id' => null]);

        Schema::table('workspaces', function (Blueprint $table) use ($references) {
            $table->foreign('staff_id')->references('id')->on($references)->nullOnDelete();
        });
    }
};
