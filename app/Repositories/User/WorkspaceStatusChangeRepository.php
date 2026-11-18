<?php

namespace App\Repositories\User;

use App\Models\User\Workspace;
use App\Models\User\WorkspaceStatusChange;
use App\Support\BusinessStatus;
use Illuminate\Database\Eloquent\Collection;

/** The changes to businesses' statuses (Doc 15 section 15.6), newest last. */
class WorkspaceStatusChangeRepository
{
    public function create(array $data): WorkspaceStatusChange
    {
        return WorkspaceStatusChange::create($data);
    }

    /** The change that set $workspace's status as it is now, if any did. */
    public function latestFor(Workspace $workspace): ?WorkspaceStatusChange
    {
        return $workspace->statusChanges()->latest('id')->first();
    }

    /** @return Collection<int, WorkspaceStatusChange> newest first */
    public function historyFor(Workspace $workspace, int $limit = 20): Collection
    {
        return $workspace->statusChanges()->latest('id')->limit($limit)->get();
    }

    public function markOwnerNotified(WorkspaceStatusChange $change): void
    {
        $change->forceFill(['owner_notified' => true])->save();
    }

    /** @return Collection<int, Workspace> the businesses whose scheduled closing day is $today or past, with their owners */
    public function dueClosures(string $today): Collection
    {
        return Workspace::query()
            ->where('status', BusinessStatus::CLOSURE_SCHEDULED)
            ->whereHas('statusChanges', fn ($query) => $query
                ->where('status', BusinessStatus::CLOSURE_SCHEDULED)
                ->whereDate('closes_on', '<=', $today)
                ->whereRaw('workspace_status_changes.id = (select max(latest.id) from workspace_status_changes as latest where latest.workspace_id = workspace_status_changes.workspace_id)'))
            ->with('user')
            ->get();
    }
}
