<?php

namespace App\Repositories\User;

use App\Models\User\SalesDocument;
use App\Models\User\SalesTeam;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/** A business's sales teams, and the members who can sell for it (Doc 16 Phase A). */
class SalesTeamRepository
{
    /** $workspace's teams by name, each with its members' ids and how many documents name it; $activeOnly for a form's choices. */
    public function forWorkspace(Workspace $workspace, bool $activeOnly = false): Collection
    {
        return SalesTeam::query()
            ->where('workspace_id', $workspace->id)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->with('members:id,first_name,last_name')
            ->withCount('documents')
            ->orderBy('name')
            ->get();
    }

    public function find(Workspace $workspace, int $id): ?SalesTeam
    {
        return SalesTeam::query()->where('workspace_id', $workspace->id)->find($id);
    }

    /** Whether $id is one of $workspace's teams switched on. */
    public function isActive(Workspace $workspace, int $id): bool
    {
        return SalesTeam::query()->where('workspace_id', $workspace->id)->where('is_active', true)->whereKey($id)->exists();
    }

    /** Whether $workspace has a team called $name, other than $exceptId. */
    public function nameTaken(Workspace $workspace, string $name, ?int $exceptId = null): bool
    {
        return SalesTeam::query()->where('workspace_id', $workspace->id)->where('name', $name)->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists();
    }

    public function create(Workspace $workspace, array $data, array $memberIds): SalesTeam
    {
        $team = SalesTeam::create([...$data, 'workspace_id' => $workspace->id]);
        $team->members()->sync($memberIds);

        return $team;
    }

    public function update(SalesTeam $team, array $data, array $memberIds): SalesTeam
    {
        $team->update($data);
        $team->members()->sync($memberIds);

        return $team;
    }

    public function delete(SalesTeam $team): void
    {
        $team->delete();
    }

    /** Whether a sales document names $team. */
    public function isUsed(SalesTeam $team): bool
    {
        return SalesDocument::query()->where('sales_team_id', $team->id)->exists();
    }

    /** The people who can sell for $workspace: its owner and active members, by name, as [id => user id, name]. */
    public function salespeople(Workspace $workspace): BaseCollection
    {
        $members = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', WorkspaceMember::ACTIVE)
            ->with('user:id,first_name,last_name')
            ->get(['id', 'user_id'])
            ->filter(fn (WorkspaceMember $member) => $member->user !== null)
            ->map(fn (WorkspaceMember $member) => ['id' => $member->user_id, 'name' => $member->user->name]);

        if ($workspace->user && ! $members->contains('id', $workspace->user_id)) {
            $members->push(['id' => $workspace->user_id, 'name' => $workspace->user->name]);
        }

        return $members->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /** Whether $userId is a member of $workspace - active or not: a document keeps a salesperson who has left. */
    public function isMember(Workspace $workspace, int $userId): bool
    {
        return $workspace->user_id === $userId || WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('user_id', $userId)->exists();
    }

    /** Which of $userIds are $workspace's owner or its active members. @return list<int> */
    public function activeMemberIds(Workspace $workspace, array $userIds): array
    {
        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', WorkspaceMember::ACTIVE)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->push(...(in_array($workspace->user_id, $userIds, true) ? [$workspace->user_id] : []))
            ->unique()
            ->values()
            ->all();
    }
}
