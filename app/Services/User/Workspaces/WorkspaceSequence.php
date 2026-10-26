<?php

namespace App\Services\User\Workspaces;

use App\Repositories\User\WorkspaceSequenceRepository;
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
    public function __construct(
        private WorkspaceSequenceRepository $sequences,
    ) {}

    public function next(int $workspaceId, string $name): int
    {
        return DB::transaction(function () use ($workspaceId, $name): int {
            $now = now();

            // Made on first use. Two first uses at once both try; one wins.
            $this->sequences->ensure($workspaceId, $name, $now);

            $counter = $this->sequences->lock($workspaceId, $name);

            $next = $counter->last_value + 1;

            $this->sequences->setLastValue($counter->id, $next, $now);

            return $next;
        });
    }
}
