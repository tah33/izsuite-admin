<?php

namespace App\Repositories\User;

use App\Models\User\User;
use App\Models\User\UserStaff;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/**
 * Staff belong to one business (workspace). Every lookup is scoped to it, so
 * another business's staff member - the same owner's or anyone else's - is
 * simply not found.
 */
class UserStaffRepository
{
    /**
     * Alphabetical, with the id as tie-breaker so two staff members sharing a
     * name always come back in the same order.
     */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->staff()->orderBy('name')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?UserStaff
    {
        return $workspace->staff()->find($id);
    }

    /**
     * Made through the business, so its workspace and owner are always the
     * business's own - never read from $data.
     */
    public function create(Workspace $workspace, array $data): UserStaff
    {
        return $workspace->staff()->create([...$this->tidy($data), 'user_id' => $workspace->user_id]);
    }

    public function update(UserStaff $staff, array $data): UserStaff
    {
        $staff->update($this->tidy($data));

        return $staff;
    }

    /**
     * The business's staff member matching $match, made from $match and $values
     * if there is none yet - for something that may run twice and must still
     * leave only one.
     */
    public function firstOrCreateFor(Workspace $workspace, array $match, array $values = []): UserStaff
    {
        return $workspace->staff()->firstOrCreate($match, [...$this->tidy($values), 'user_id' => $workspace->user_id]);
    }

    /**
     * Adds a workspace to the ones a staff member is assigned to. Already there,
     * it is left alone - nothing is written.
     */
    public function assignWorkspace(UserStaff $staff, int $workspaceId): void
    {
        $assigned = $staff->assigned_workspaces ?? [];

        if (in_array($workspaceId, $assigned, true)) {
            return;
        }

        $this->update($staff, ['assigned_workspaces' => [...$assigned, $workspaceId]]);
    }

    public function delete(UserStaff $staff): void
    {
        $staff->delete();
    }

    /**
     * Takes a workspace that has been deleted out of the lists that held it -
     * across all of the owner's businesses, since staff of one may be assigned
     * to another. Only the owner's staff: nobody else's list can name their
     * workspace.
     */
    public function forgetWorkspace(User $user, int $workspaceId): void
    {
        UserStaff::where('user_id', $user->id)
            ->whereJsonContains('assigned_workspaces', $workspaceId)
            ->get()
            ->each(fn (UserStaff $staff) => $staff->update([
                'assigned_workspaces' => array_values(array_diff($staff->assigned_workspaces ?? [], [$workspaceId])),
            ]));
    }

    /**
     * The assignments are lists of ids and are stored as exactly that: whole
     * numbers, each once, in order - whatever shape they arrived in ("2" as text,
     * out of order, null for none). Two requests that mean the same list then
     * store the same JSON, and an edit that changes nothing is seen to change
     * nothing. A key that was not sent is left out, so it is left alone.
     */
    private function tidy(array $data): array
    {
        foreach (['assigned_apps', 'assigned_workspaces'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $ids = array_values(array_unique(array_map('intval', $data[$key] ?? [])));

            sort($ids);

            $data[$key] = $ids;
        }

        return $data;
    }
}
