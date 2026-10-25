<?php

namespace App\Services\User\Workspaces;

use Illuminate\Support\Facades\DB;

/**
 * Hands out a business's running numbers (see the workspace_sequences table):
 * next() gives the next one of a counter, starting at 1.
 *
 * The counter's row is locked while it moves on, so two records made at the
 * same moment never get the same number. Called inside the transaction that
 * saves the record, a failed save gives its number back.
 */
class WorkspaceSequence
{
    public function next(int $workspaceId, string $name): int
    {
        return DB::transaction(function () use ($workspaceId, $name): int {
            $now = now();

            // Made on first use. Two first uses at once both try; one wins.
            DB::table('workspace_sequences')->insertOrIgnore([
                'workspace_id' => $workspaceId,
                'name'         => $name,
                'last_value'   => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            $counter = DB::table('workspace_sequences')
                ->where('workspace_id', $workspaceId)
                ->where('name', $name)
                ->lockForUpdate()
                ->first();

            $next = $counter->last_value + 1;

            DB::table('workspace_sequences')->where('id', $counter->id)->update(['last_value' => $next, 'updated_at' => $now]);

            return $next;
        });
    }
}
