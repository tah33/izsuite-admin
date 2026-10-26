<?php

namespace App\Repositories\User;

use App\Models\Billing\Package;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * The account's first workspace - the one registration made, and the one
     * the API answers for when no business is picked.
     */
    public function oldestForUser(User $user): ?Workspace
    {
        return Workspace::where('user_id', $user->id)->orderBy('id')->first();
    }

    /** oldestForUser(), with the plan - the business a request answers for when none is picked. */
    public function oldestForUserWithPackage(User $user): ?Workspace
    {
        return Workspace::where('user_id', $user->id)->with('package')->orderBy('id')->first();
    }

    /** One of the account's workspaces, with its plan - the business a request picked. */
    public function findForUserWithPackage(User $user, int $id): ?Workspace
    {
        return Workspace::where('user_id', $user->id)->with('package')->find($id);
    }

    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->update($data);

        // Reloaded, not just returned: the app it was fetched with may be one it
        // no longer has.
        return $workspace->load(self::RELATIONS);
    }

    /** Sets $attributes, fillable or not, and saves - the instance keeps its changes for reading. */
    public function forceUpdate(Workspace $workspace, array $attributes): void
    {
        $workspace->forceFill($attributes)->save();
    }

    public function delete(Workspace $workspace): void
    {
        $workspace->delete();
    }

    /**
     * The admin panel's list of every business, newest first, with its owner,
     * its plan and the apps it has on - searched by its name or its owner's
     * email, and filtered by the key of its plan.
     */
    public function paginateForAdmin(string $search, ?string $package, int $perPage): LengthAwarePaginator
    {
        return Workspace::query()
            ->with([
                'user:id,first_name,last_name,email',
                'package:id,key,name',
                'workspaceApps' => fn ($q) => $q->where('status', WorkspaceApp::STATUS_ACTIVE)->with('app:id,name,edition,sort_order'),
            ])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"))))
            ->when($package, fn ($q) => $q->whereHas('package', fn ($p) => $p->where('key', $package)))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Any business, with its plan - for the admin panel. */
    public function findWithPackageOrFail(int $id): Workspace
    {
        return Workspace::with('package')->findOrFail($id);
    }

    /** The business's plan, read afresh rather than from the loaded relation. */
    public function packageOf(Workspace $workspace): ?Package
    {
        return $workspace->package()->first();
    }

    public function setPackage(Workspace $workspace, Package $package): void
    {
        $workspace->package()->associate($package)->save();
    }

    /** Its sidebar changed: a new version, so the cached one is not used again. */
    public function bumpNavVersion(Workspace $workspace): void
    {
        $workspace->increment('nav_version');
    }
}
