<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
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
            $roles = $this->staffService->assignableRoles();

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
            $this->staffService->create($request->validated());

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
            $staffUser = $this->staffService->findEditable($id);

            if (! $staffUser) {
                abort(404);
            }

            $roles     = $this->staffService->assignableRoles();

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
            $staffUser = $this->staffService->findEditable($id);

            if (! $staffUser) {
                abort(403);
            }

            $this->staffService->update($staffUser, $request->validated());

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
            $staffUser = $this->staffService->findEditable($id);

            if (! $staffUser) {
                abort(403);
            }

            $this->staffService->toggleStatus($staffUser);

            $status    = $staffUser->status === 'active' ? 'activated' : 'deactivated';

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
            $staffUser = $this->staffService->findEditable($id);

            if (! $staffUser) {
                abort(403);
            }

            if ($problem = $this->staffService->deletionProblem($staffUser)) {
                return $this->adminFailure(request(), $problem, 400);
            }

            $this->staffService->delete($staffUser);

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
}
