<?php

namespace App\Services\Admin;

use App\Models\Admin\Role;
use App\Repositories\Admin\RoleRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RoleService
{
    public function __construct(
        protected RoleRepository $roleRepo,
    ) {}

    /**
     * Get all manageable roles.
     */
    public function getAll(): Collection
    {
        return $this->roleRepo->getAll();
    }

    /**
     * Find role by ID.
     */
    public function find(int $id): ?Role
    {
        return $this->roleRepo->find($id);
    }

    /**
     * Find a role that may be changed: any but Super Admin, which never is.
     */
    public function findEditable(int $id): ?Role
    {
        $role = $this->find($id);

        return $role && ! $role->isSuperAdmin() ? $role : null;
    }

    /**
     * Create a new role with auto-generated slug.
     */
    public function create(array $data): Role
    {
        $data['slug']        = Str::slug($data['name']);
        $data['permissions'] = $data['permissions'] ?? [];

        $role = $this->roleRepo->create($data);

        ActivityLogService::record('created', "Created role \"{$role->name}\"", $role);

        return $role;
    }

    /**
     * Update a role.
     */
    public function update(Role $role, array $data): Role
    {
        $data['slug']        = Str::slug($data['name']);
        $data['permissions'] = $data['permissions'] ?? [];

        $updated = $this->roleRepo->update($role, $data);

        ActivityLogService::record('updated', "Updated role \"{$role->name}\"", $role);

        return $updated;
    }

    /**
     * Delete a role if no users are assigned.
     */
    public function delete(Role $role): bool
    {
        $name    = $role->name;
        $deleted = $this->roleRepo->delete($role);

        ActivityLogService::record('deleted', "Deleted role \"{$name}\"");

        return $deleted;
    }

    /**
     * Why $role cannot be deleted, if it cannot.
     */
    public function deletionProblem(Role $role): ?string
    {
        return $role->users_count > 0 ? 'Cannot delete a role that has users assigned.' : null;
    }

    /**
     * Build grouped permission list from named admin routes.
     */
    public function permissionGroups(): array
    {
        $routes = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, $name) => str_starts_with($name, 'admin.'))
            ->keys()
            ->sort();

        $groups = [];
        foreach ($routes as $routeName) {
            // e.g. admin.users.index → "Users"
            $parts             = explode('.', $routeName);
            $module            = ucfirst($parts[1] ?? 'General');
            $action            = $parts[2] ?? $routeName;

            $groups[$module][] = [
                'name'  => $routeName,
                'label' => ucfirst(str_replace('-', ' ', $action)),
            ];
        }

        ksort($groups);

        return $groups;
    }
}
