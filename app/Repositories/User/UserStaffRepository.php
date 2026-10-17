<?php

namespace App\Repositories\User;

use App\Models\User\User;
use App\Models\User\UserStaff;
use Illuminate\Database\Eloquent\Collection;

class UserStaffRepository
{
    /**
     * Alphabetical, with the id as tie-breaker so two staff members sharing a
     * name always come back in the same order.
     */
    public function getForUser(User $user): Collection
    {
        return UserStaff::where('user_id', $user->id)->orderBy('name')->orderBy('id')->get();
    }

    /**
     * Scoped to the owner: another account's id finds nothing, so callers
     * cannot tell "not yours" from "does not exist".
     */
    public function findForUser(User $user, int $id): ?UserStaff
    {
        return UserStaff::where('user_id', $user->id)->find($id);
    }

    /**
     * Created through the owner's relation, so user_id is always the caller's -
     * it is never read from $data.
     */
    public function create(User $user, array $data): UserStaff
    {
        return $user->userStaff()->create($this->tidy($data));
    }

    public function update(UserStaff $staff, array $data): UserStaff
    {
        $staff->update($this->tidy($data));

        return $staff;
    }

    /**
     * The owner's staff member matching $match, made from $match and $values if
     * there is none yet - for something that may run twice and must still leave
     * only one.
     */
    public function firstOrCreateForUser(User $user, array $match, array $values = []): UserStaff
    {
        return $user->userStaff()->firstOrCreate($match, $this->tidy($values));
    }

    /**
     * The owner's first staff member with this e-mail - the oldest, should the
     * same address have been used twice.
     */
    public function findByEmailForUser(User $user, string $email): ?UserStaff
    {
        return UserStaff::where('user_id', $user->id)->where('email', $email)->orderBy('id')->first();
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
     * Takes a workspace that has been deleted out of the lists that held it.
     * Only the caller's staff: nobody else's list can name their workspace.
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
