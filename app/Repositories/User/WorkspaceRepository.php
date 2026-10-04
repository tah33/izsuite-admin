<?php

namespace App\Repositories\User;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceRepository
{
    /**
     * What a workspace comes back with - its app and its staff member - and only
     * the columns a name is made of, so nothing else about either can reach the
     * response, and each row can show both names without a query of its own.
     */
    private const RELATIONS = ['app:id,name', 'staff:id,first_name,last_name'];

    /**
     * Alphabetical, with the id as tie-breaker so two workspaces sharing a name
     * always come back in the same order.
     */
    public function getForUser(User $user): Collection
    {
        return Workspace::with(self::RELATIONS)->where('user_id', $user->id)->orderBy('name')->orderBy('id')->get();
    }

    /**
     * Scoped to the owner: another account's id finds nothing, so callers
     * cannot tell "not yours" from "does not exist".
     */
    public function findForUser(User $user, int $id): ?Workspace
    {
        return Workspace::with(self::RELATIONS)->where('user_id', $user->id)->find($id);
    }

    public function create(User $user, array $data): Workspace
    {
        return $user->workspaces()->create($data)->load(self::RELATIONS);
    }

    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->update($data);

        // Reloaded, not just returned: the app and the staff member it was
        // fetched with may be ones it no longer has.
        return $workspace->load(self::RELATIONS);
    }

    public function delete(Workspace $workspace): void
    {
        $workspace->delete();
    }
}
