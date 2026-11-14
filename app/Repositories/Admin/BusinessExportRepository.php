<?php

namespace App\Repositories\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;

/**
 * A business's rows for its data export (Doc 15 Phase G1), table by table,
 * read in chunks so a large business is not held in memory at once.
 */
class BusinessExportRepository
{
    /** $table's column names. @return list<string> */
    public function columns(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    /** $table's rows that belong to business $workspaceId. */
    public function ownRows(string $table, int $workspaceId): LazyCollection
    {
        return DB::table($table)->where('workspace_id', $workspaceId)->orderBy('id')->lazy(500);
    }

    /** $table's rows under the rows of $parent that belong to business $workspaceId, joined by $foreignKey. */
    public function childRows(string $table, string $foreignKey, string $parent, int $workspaceId): LazyCollection
    {
        return DB::table($table)
            ->whereIn($foreignKey, DB::table($parent)->select('id')->where('workspace_id', $workspaceId))
            ->orderBy('id')
            ->lazy(500);
    }

    /** The business's members: who they are, their role, and since when - not their account's secrets. */
    public function members(int $workspaceId): LazyCollection
    {
        return DB::table('workspace_members as members')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->leftJoin('workspace_roles as roles', 'roles.id', '=', 'members.role_id')
            ->where('members.workspace_id', $workspaceId)
            ->orderBy('members.id')
            ->select(['members.id', 'users.first_name', 'users.last_name', 'users.email', 'users.phone', 'roles.name as role', 'members.status', 'members.created_at'])
            ->lazy(500);
    }
}
