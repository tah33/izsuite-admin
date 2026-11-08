<?php

namespace App\Services\Api\Roles;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceRole;
use App\Repositories\User\WorkspaceRepository;
use App\Repositories\User\WorkspaceRoleRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Permissions;
use App\Support\SettingChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Settings > Users & Roles > Roles (Doc 12 sections 10, 11 and 15): the
 * business's default templates and the roles it adds, each allowing a set of
 * permissions - or every one there is, with full access.
 *
 * A role shows and takes the permissions of the apps on now; what it holds
 * for an app turned off is kept for when the app comes back. Whatever a
 * permission needs is added with it (Permissions::expand). A change reaches
 * the members who hold the role at once - their sidebars are rebuilt - and
 * nothing already recorded.
 *
 * Business Owner is protected: it cannot be changed, copied, switched off or
 * deleted. A role someone holds cannot be switched off or deleted until they
 * are given another, and nobody changes the role they hold themselves; a
 * template can be switched off, but only a role the business added can be
 * deleted.
 */
class RoleService
{
    use RefusesConflicts;

    public function __construct(
        protected WorkspaceRoleRepository $roleRepository,
        protected WorkspaceRepository $workspaceRepository,
        protected WorkspaceAccess $access,
    ) {}

    public function list(Workspace $workspace): Collection
    {
        return $this->roleRepository->getFor($workspace);
    }

    /** The groups a role can hold, for the apps on now. */
    public function permissions(Workspace $workspace): array
    {
        return Permissions::groups($this->families($workspace));
    }

    public function show(Workspace $workspace, int $id): WorkspaceRole
    {
        return $this->find($workspace, $id);
    }

    /** $data is validated (see SaveRoleRequest): its name among them. */
    public function create(Workspace $workspace, array $data): WorkspaceRole
    {
        $role = $this->roleRepository->create($workspace, [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'full_access' => (bool) ($data['full_access'] ?? false),
            'permissions' => ($data['full_access'] ?? false) ? [] : Permissions::expand(array_values(array_intersect($data['permissions'] ?? [], $this->available($workspace)))),
        ]);

        ActivityLogService::record('created', "Added role {$this->quoted($role)}", $role, ['source' => 'api']);

        return $this->find($workspace, $role->id);
    }

    public function update(Workspace $workspace, int $id, array $data, User $actor): WorkspaceRole
    {
        $role = $this->changeable($workspace, $id, 'changed');

        // Nobody changes their own role, or what it allows (Doc 12 section 22).
        if ($this->access->roleOf($actor, $workspace) === $role->id) {
            abort(403, 'You cannot change the role you hold. Someone with another role can.');
        }

        $before    = $this->state($role);
        $available = $this->available($workspace);
        $full      = (bool) ($data['full_access'] ?? false);

        // What it holds for apps off now is kept; the rest is what was sent.
        $kept = array_values(array_diff($role->permissions ?? [], $available));

        $this->roleRepository->update($role, [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'full_access' => $full,
            'permissions' => $full ? [] : Permissions::expand([...$kept, ...array_intersect($data['permissions'] ?? [], $available)]),
        ]);

        $this->workspaceRepository->bumpNavVersion($workspace);

        ActivityLogService::record('updated', "Updated role {$this->quoted($role)}", $role, array_filter([
            'source'  => 'api',
            'changes' => $this->changes($before, $this->state($role)),
        ]));

        return $this->find($workspace, $role->id);
    }

    /** "Copy as New Role": the same permissions under "Copy of …", the business's own. */
    public function copy(Workspace $workspace, int $id): WorkspaceRole
    {
        $source = $this->find($workspace, $id);

        if ($source->isOwner()) {
            $this->refuse('Business Owner cannot be copied. Copy Administrator, or add a role with full access.');
        }

        $copy = $this->roleRepository->create($workspace, [
            'name'        => $this->copyName($workspace, $source->name),
            'description' => $source->description,
            'full_access' => $source->full_access,
            'permissions' => $source->permissions ?? [],
        ]);

        ActivityLogService::record('created', "Copied role {$this->quoted($source)} as {$this->quoted($copy)}", $copy, ['source' => 'api']);

        return $this->find($workspace, $copy->id);
    }

    public function activate(Workspace $workspace, int $id): WorkspaceRole
    {
        return $this->setActive($workspace, $this->changeable($workspace, $id, 'switched off'), true);
    }

    /** Only once nobody holds it (Doc 12 section 10): they are given another role first. */
    public function deactivate(Workspace $workspace, int $id): WorkspaceRole
    {
        $role = $this->changeable($workspace, $id, 'switched off');

        if ($role->members_count > 0) {
            $this->refuse("{$this->held($role)} Give them another role before switching this one off.");
        }

        return $this->setActive($workspace, $role, false);
    }

    /** Only a role the business added, and nobody holds (Doc 12 section 10). */
    public function delete(Workspace $workspace, int $id): void
    {
        $role = $this->changeable($workspace, $id, 'deleted');

        if ($role->isTemplate()) {
            $this->refuse('A default role cannot be deleted. Switch it off instead.');
        }

        if ($role->members_count > 0) {
            $this->refuse("{$this->held($role)} Give them another role before deleting this one.");
        }

        $this->roleRepository->delete($role);

        ActivityLogService::record('deleted', "Deleted role {$this->quoted($role)}", $role, ['source' => 'api']);
    }

    private function setActive(Workspace $workspace, WorkspaceRole $role, bool $active): WorkspaceRole
    {
        if ($role->is_active !== $active) {
            $this->roleRepository->update($role, ['is_active' => $active]);
            $this->workspaceRepository->bumpNavVersion($workspace);

            ActivityLogService::record($active ? 'activated' : 'deactivated', ($active ? 'Switched on' : 'Switched off')." role {$this->quoted($role)}", $role, ['source' => 'api']);
        }

        return $this->find($workspace, $role->id);
    }

    /** The role, unless it is Business Owner, which cannot be $what. */
    private function changeable(Workspace $workspace, int $id, string $what): WorkspaceRole
    {
        $role = $this->find($workspace, $id);

        if ($role->isOwner()) {
            $this->refuse("Business Owner is protected, so it cannot be {$what}.");
        }

        return $role;
    }

    /** "Copy of Sales User", or "Copy of Sales User (2)" when that is taken. */
    private function copyName(Workspace $workspace, string $name): string
    {
        $base = Str::limit('Copy of '.$name, 90, '');
        $copy = $base;

        for ($n = 2; $this->roleRepository->nameTaken($workspace, $copy); $n++) {
            $copy = "{$base} ({$n})";
        }

        return $copy;
    }

    /** @return list<string> the app families on now */
    private function families(Workspace $workspace): array
    {
        return WorkspaceApps::for($workspace)->active()->keys()->all();
    }

    /** @return list<string> */
    private function available(Workspace $workspace): array
    {
        return Permissions::keys($this->families($workspace));
    }

    private function state(WorkspaceRole $role): array
    {
        return [
            'name'        => $role->name,
            'description' => $role->description,
            'full_access' => $role->full_access,
            'permissions' => $role->full_access ? [] : ($role->permissions ?? []),
        ];
    }

    /**
     * What a save changed, for the Activity Log (Doc 12 section 24): the name,
     * description and full access, then each permission granted or taken
     * away - On or Off - unless full access holds them all either side.
     */
    private function changes(array $before, array $after): array
    {
        $changes = SettingChanges::between(
            array_diff_key($before, ['permissions' => true]),
            array_diff_key($after, ['permissions' => true]),
        );

        if ($before['full_access'] || $after['full_access']) {
            return $changes;
        }

        foreach (Permissions::keys() as $permission) {
            $was = in_array($permission, $before['permissions'], true);
            $is  = in_array($permission, $after['permissions'], true);

            if ($was !== $is) {
                $changes[] = ['setting' => Permissions::label($permission), 'from' => $was, 'to' => $is];
            }
        }

        return $changes;
    }

    private function held(WorkspaceRole $role): string
    {
        return $role->members_count === 1 ? '1 member holds this role.' : "{$role->members_count} members hold this role.";
    }

    /** Another business's role answers like one that does not exist. */
    private function find(Workspace $workspace, int $id): WorkspaceRole
    {
        return $this->roleRepository->findFor($workspace, $id) ?? abort(404, 'Role not found.');
    }

    private function quoted(WorkspaceRole $role): string
    {
        return '"'.Str::limit($role->name, 50).'"';
    }
}
