<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A workspace no longer has a staff member of its own: which staff work in which
 * workspace is set from the staff side now, in user_staff.assigned_workspaces,
 * where a staff member can have any number of them.
 *
 * So workspaces.staff_id goes - but not before what it holds is carried over:
 * each workspace is added to the list of workspaces of the staff member it had.
 * (Only when the staff member belongs to the same account as the workspace,
 * which the API has always made sure of.)
 *
 * Rolling back puts the empty column and its foreign key back. The old one-to-one
 * links cannot be rebuilt from the lists - a workspace may now be in several
 * staff members' lists, or in none - so they come back unset.
 */
return new class extends Migration
{
    public function up(): void
    {
        $links = DB::table('workspaces')
            ->whereNotNull('staff_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'staff_id'])
            ->groupBy('staff_id');

        foreach ($links as $staffId => $workspaces) {
            $staff = DB::table('user_staff')->where('id', $staffId)->first(['user_id', 'assigned_workspaces']);

            if (! $staff) {
                continue;
            }

            $carried = $workspaces->where('user_id', $staff->user_id)->pluck('id');

            // Nothing of this account's to carry over: the row is left as it is.
            if ($carried->isEmpty()) {
                continue;
            }

            $assigned = collect(json_decode($staff->assigned_workspaces ?? '[]', true) ?: [])
                ->merge($carried)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();

            DB::table('user_staff')->where('id', $staffId)->update(['assigned_workspaces' => $assigned->toJson()]);
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn('staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('user_id')->constrained('user_staff')->nullOnDelete();
        });
    }
};
