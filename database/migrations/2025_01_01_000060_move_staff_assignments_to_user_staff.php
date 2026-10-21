<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which apps and workspaces a staff member works in, kept on the staff member.
 *
 * assigned_apps and assigned_workspaces are lists of ids, stored as JSON. The
 * UserStaff model casts both to arrays, and the API, its repository and the
 * frontend's Staff form already read and write them - but no migration ever
 * created them, so saving a staff member failed with "Unknown column".
 * Nullable: a row from before this has neither, and the API answers a missing
 * list as an empty one.
 *
 * workspaces.staff_id goes in the same step. A workspace has no staff member of
 * its own any more - which staff work in it is set from the staff side, as
 * their assigned workspaces - and nothing writes the column.
 *
 * Each step checks first: a database that was given these columns by hand, or
 * by a migration that never reached the repository, must not fail on them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_staff', function (Blueprint $table) {
            if (! Schema::hasColumn('user_staff', 'assigned_apps')) {
                $table->json('assigned_apps')->nullable()->after('status');
            }

            if (! Schema::hasColumn('user_staff', 'assigned_workspaces')) {
                $table->json('assigned_workspaces')->nullable()->after('assigned_apps');
            }
        });

        if (Schema::hasColumn('workspaces', 'staff_id')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->dropForeign(['staff_id']);
                $table->dropColumn('staff_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('user_id')->constrained('user_staff')->nullOnDelete();
        });

        Schema::table('user_staff', function (Blueprint $table) {
            $table->dropColumn(['assigned_apps', 'assigned_workspaces']);
        });
    }
};
