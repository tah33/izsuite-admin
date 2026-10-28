<?php

namespace App\Repositories\User;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * The workspace_sequences table: one counter per business and name, holding
 * the last number it gave out.
 */
class WorkspaceSequenceRepository
{
    /** Makes the counter at 0, unless it is there already. */
    public function ensure(int $workspaceId, string $name, DateTimeInterface $now): void
    {
        DB::table('workspace_sequences')->insertOrIgnore([
            'workspace_id' => $workspaceId,
            'name'         => $name,
            'last_value'   => 0,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    /** The last number the counter gave out, without locking it - 0 for one not made yet. */
    public function lastValue(int $workspaceId, string $name): int
    {
        return (int) DB::table('workspace_sequences')
            ->where('workspace_id', $workspaceId)
            ->where('name', $name)
            ->value('last_value');
    }

    /** The counter's row, locked until the transaction ends. */
    public function lock(int $workspaceId, string $name): ?object
    {
        return DB::table('workspace_sequences')
            ->where('workspace_id', $workspaceId)
            ->where('name', $name)
            ->lockForUpdate()
            ->first();
    }

    public function setLastValue(int $id, int $value, DateTimeInterface $now): void
    {
        DB::table('workspace_sequences')->where('id', $id)->update(['last_value' => $value, 'updated_at' => $now]);
    }
}
