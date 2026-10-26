<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business data belongs to the business, not to the account that signs in
 * (Doc 01 section 2: data stays apart between Business Workspaces). Locations,
 * business settings and staff each get the workspace they belong to, and the
 * APIs answer for the business picked in the top bar (X-Workspace-Id).
 *
 * Existing rows move to their account's oldest workspace - the one the API
 * already falls back to when no business is picked. An account that has such
 * rows but no workspace at all is given one first, on Billing Starter, named
 * after its business when it has saved one. Made here with plain inserts, it
 * has no apps on yet: AppCatalogSeeder turns on every workspace's Lite apps,
 * so run it after migrating (it is safe to run again).
 *
 * user_id stays on all three tables: it is the account that owns the business,
 * and account-wide lookups (and the cascade when an account is deleted) use it.
 *
 * Deleting a workspace now deletes its locations, settings and staff with it.
 * business_settings becomes one row per workspace instead of one per account.
 *
 * The column is added empty and filled before the foreign key goes on: MySQL
 * refuses to change a column that a foreign key already uses.
 */
return new class extends Migration
{
    private const TABLES = ['locations', 'business_settings', 'user_staff'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('workspace_id')->nullable()->after('user_id');
            });
        }

        $this->giveOwnersWithoutOneAWorkspace();

        foreach (self::TABLES as $table) {
            DB::table($table)->whereNull('workspace_id')->update([
                'workspace_id' => DB::raw("(select min(w.id) from workspaces w where w.user_id = {$table}.user_id)"),
            ]);
        }

        foreach (['locations', 'user_staff'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('workspace_id')->nullable(false)->change();
                $t->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            });
        }

        // The unique index on workspace_id also serves its foreign key. user_id
        // keeps its foreign key, so it gets a plain index before losing the
        // unique one that served it.
        Schema::table('business_settings', function (Blueprint $t) {
            $t->unsignedBigInteger('workspace_id')->nullable(false)->change();
            $t->unique('workspace_id');
            $t->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $t->index('user_id');
        });

        Schema::table('business_settings', function (Blueprint $t) {
            $t->dropUnique(['user_id']);
        });
    }

    /**
     * Back to one set of business settings per account: each account keeps the
     * settings of its oldest workspace, and those of its other workspaces are
     * deleted. Locations and staff simply lose the column.
     */
    public function down(): void
    {
        $oldest = DB::table('workspaces')->selectRaw('min(id)')->groupBy('user_id');

        DB::table('business_settings')->whereNotIn('workspace_id', $oldest)->delete();

        Schema::table('business_settings', function (Blueprint $t) {
            $t->unique('user_id');
        });

        Schema::table('business_settings', function (Blueprint $t) {
            $t->dropIndex(['user_id']);
            $t->dropForeign(['workspace_id']);
            $t->dropUnique(['workspace_id']);
            $t->dropColumn('workspace_id');
        });

        foreach (['locations', 'user_staff'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['workspace_id']);
                $t->dropColumn('workspace_id');
            });
        }
    }

    /** One Billing Starter workspace for each account that has business data but no workspace. */
    private function giveOwnersWithoutOneAWorkspace(): void
    {
        $owners = collect(self::TABLES)
            ->flatMap(fn (string $table) => DB::table($table)->distinct()->pluck('user_id'))
            ->unique()
            ->diff(DB::table('workspaces')->distinct()->pluck('user_id'));

        $starter = DB::table('packages')->where('key', 'starter')->value('id');

        foreach ($owners as $userId) {
            DB::table('workspaces')->insert([
                'user_id'     => $userId,
                'name'        => DB::table('business_settings')->where('user_id', $userId)->value('business_name') ?: 'Billing Starter',
                'package_id'  => $starter,
                'nav_version' => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
};
