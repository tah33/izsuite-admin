<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Services\Admin\RoleService;

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
            $permissionGroups = $this->roleService->permissionGroups();

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
            $this->roleService->create($request->validated());

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
            $role             = $this->roleService->findEditable($id);

            if (! $role) {
                abort(404);
            }

            $permissionGroups = $this->roleService->permissionGroups();

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
            $role = $this->roleService->findEditable($id);

            if (! $role) {
                abort(403);
            }

            $this->roleService->update($role, $request->validated());

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
            $role = $this->roleService->findEditable($id);

            if (! $role) {
                abort(403);
            }

            if ($problem = $this->roleService->deletionProblem($role)) {
                return redirect()->route('admin.roles.index')
                    ->with('error', $problem);
            }

            $this->roleService->delete($role);

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role deleted successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
