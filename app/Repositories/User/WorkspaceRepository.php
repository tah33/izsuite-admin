<?php

namespace App\Repositories\User;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceRepository
{
    /**
     * What a workspace comes back with - its plan - and only the columns the
     * response shows, so each row can show it without a query of its own.
     */
    private const RELATIONS = ['package:id,key,name'];

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

    /**
     * The owner's workspace matching $attributes, made from them if there is none
     * yet - for something that may run twice and must still leave only one.
     */
    public function firstOrCreateForUser(User $user, array $attributes): Workspace
    {
        return $user->workspaces()->firstOrCreate($attributes);
    }

    /**
     * The owner's first workspace with this name. Names are not unique, so the
     * oldest one is the answer when there are several.
     */
    public function findByNameForUser(User $user, string $name): ?Workspace
    {
        return Workspace::where('user_id', $user->id)->where('name', $name)->orderBy('id')->first();
    }

    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->update($data);

        // Reloaded, not just returned: the app it was fetched with may be one it
        // no longer has.
        return $workspace->load(self::RELATIONS);
    }

    public function delete(Workspace $workspace): void
    {
        $workspace->delete();
    }
}
