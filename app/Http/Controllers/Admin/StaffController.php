<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\Admin\Role;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\StaffService;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(
        protected StaffService $staffService,
    ) {}

    /**
     * List all staff users.
     */
    public function index(Request $request)
    {
        try {
            $staff = $this->staffService->getFilteredStaff(
                $request->query('search'),
            );

            return view('admin.staff.index', compact('staff'));

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
            $roles = $this->getAdminRoles();

            return view('admin.staff.create', compact('roles'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store a new staff user.
     */
    public function store(StoreStaffRequest $request)
    {
        try {
            $validated = $request->validated();

            // Prevent assigning Super Admin role
            if ((int) $validated['role_id'] === Role::SUPER_ADMIN_ID) {
                abort(403);
            }

            $staff     = $this->staffService->create($validated);

            ActivityLogService::record('created', "Created staff member \"{$validated['name']}\"", $staff);

            return $this->adminSuccess(
                $request,
                'Staff member created successfully.',
                route('admin.staff.index')
            );

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
            $staffUser = $this->staffService->find($id);

            if (! $staffUser || $staffUser->isSuperAdmin()) {
                abort(404);
            }

            $roles     = $this->getAdminRoles();

            return view('admin.staff.edit', compact('staffUser', 'roles'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update a staff user.
     */
    public function update(UpdateStaffRequest $request, int $id)
    {
        try {
            $staffUser = $this->staffService->find($id);

            if (! $staffUser || $staffUser->isSuperAdmin()) {
                abort(403);
            }

            $validated = $request->validated();

            // Prevent assigning Super Admin role
            if ((int) $validated['role_id'] === Role::SUPER_ADMIN_ID) {
                abort(403);
            }

            $this->staffService->update($staffUser, $validated);

            ActivityLogService::record('updated', "Updated staff member \"{$staffUser->name}\"", $staffUser);

            return $this->adminSuccess(
                $request,
                'Staff member updated successfully.',
                route('admin.staff.index')
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Toggle staff user active/inactive status.
     */
    public function toggleStatus(int $id)
    {
        try {
            $staffUser = $this->staffService->find($id);

            if (! $staffUser || $staffUser->isSuperAdmin()) {
                abort(403);
            }

            $this->staffService->toggleStatus($staffUser);

            $status    = $staffUser->status === 'active' ? 'activated' : 'deactivated';

            ActivityLogService::record('updated', "Toggled staff \"{$staffUser->name}\" to {$status}", $staffUser);

            return $this->adminSuccess(
                request(),
                "{$staffUser->name} has been {$status}.",
                route('admin.staff.index')
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Remove the specified staff user from storage.
     */
    public function destroy(int $id)
    {
        try {
            $staffUser = $this->staffService->find($id);

            if (! $staffUser || $staffUser->isSuperAdmin()) {
                abort(403);
            }

            // Prevent deleting self
            if ($staffUser->id === auth()->id()) {
                return $this->adminFailure(request(), 'You cannot delete yourself.', 400);
            }

            $name      = $staffUser->name;
            $this->staffService->delete($staffUser);

            ActivityLogService::record('deleted', "Deleted staff member \"{$name}\"");

            return $this->adminSuccess(
                request(),
                'Staff member deleted successfully.',
                route('admin.staff.index')
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Get roles suitable for staff assignment (exclude Super Admin & User).
     */
    protected function getAdminRoles()
    {
        return Role::where('id', '!=', Role::SUPER_ADMIN_ID)
            ->whereNotIn('slug', ['recruiter', 'candidate'])
            ->orderBy('name')
            ->get();
    }
}
