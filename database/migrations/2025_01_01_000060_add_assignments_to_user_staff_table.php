<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a staff member is assigned to: the apps, and the workspaces, they work
 * in. Each is a list of ids - a staff member may have any number of either - kept
 * as a JSON array in the row itself, such as [1, 3].
 *
 * Nothing in the database points at those ids, so it is the application that
 * keeps them honest: the API accepts only an active app and one of the account's
 * own workspaces, a workspace that is deleted is taken out of the lists that held
 * it, and an app that has since gone is skipped wherever the list is shown.
 *
 * Both nullable: NULL means none, the same as an empty list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_staff', function (Blueprint $table) {
            $table->json('assigned_apps')->nullable()->after('status');
            $table->json('assigned_workspaces')->nullable()->after('assigned_apps');
        });
    }

    public function down(): void
    {
        Schema::table('user_staff', function (Blueprint $table) {
            $table->dropColumn(['assigned_apps', 'assigned_workspaces']);
        });
    }
};
