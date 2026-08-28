<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Admin\Role;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\RoleService;
use Illuminate\Support\Facades\Route;

class RoleController extends Controller
{
    public function __construct(
        protected RoleService $roleService,
    ) {}

    /**
     * List all roles.
     */
    public function index()
    {
        try {
            $roles = $this->roleService->getAll();

            return view('admin.roles.index', compact('roles'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show create form.
     */
    public function create()
    {
        try {
            $permissionGroups = $this->getAvailablePermissions();

            return view('admin.roles.create', compact('permissionGroups'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store a new role.
     */
    public function store(StoreRoleRequest $request)
    {
        try {
            $validated = $request->validated();

            $role      = $this->roleService->create($validated);

            ActivityLogService::record('created', "Created role \"{$role->name}\"", $role);

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role created successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show edit form.
     */
    public function edit(int $id)
    {
        try {
            $role             = $this->roleService->find($id);

            if (! $role || $role->isSuperAdmin()) {
                abort(404);
            }

            $permissionGroups = $this->getAvailablePermissions();

            return view('admin.roles.edit', compact('role', 'permissionGroups'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update a role.
     */
    public function update(UpdateRoleRequest $request, int $id)
    {
        try {
            $role      = $this->roleService->find($id);

            if (! $role || $role->isSuperAdmin()) {
                abort(403);
            }

            $validated = $request->validated();

            $this->roleService->update($role, $validated);

            ActivityLogService::record('updated', "Updated role \"{$role->name}\"", $role);

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role updated successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Delete a role.
     */
    public function destroy(int $id)
    {
        try {
            $role = $this->roleService->find($id);

            if (! $role || $role->isSuperAdmin()) {
                abort(403);
            }

            if ($role->users_count > 0) {
                return redirect()->route('admin.roles.index')
                    ->with('error', 'Cannot delete a role that has users assigned.');
            }

            $name = $role->name;
            $this->roleService->delete($role);

            ActivityLogService::record('deleted', "Deleted role \"{$name}\"");

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role deleted successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Build grouped permission list from named admin routes.
     */
    protected function getAvailablePermissions(): array
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
