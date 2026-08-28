<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Models\User\User;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\DepartmentService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(
        protected DepartmentService $departmentService,
    ) {}

    public function index(Request $request)
    {
        try {
            $departments = $this->departmentService->getFilteredDepartments($request->input('recruiter_id'));
            $recruiters  = User::whereHas('role', fn ($q) => $q->where('slug', 'recruiter'))->get();

            return view('admin.departments.index', compact('departments', 'recruiters'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            $recruiters = User::whereHas('role', fn ($q) => $q->where('slug', 'recruiter'))->get();

            return view('admin.departments.create', compact('recruiters'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreDepartmentRequest $request)
    {
        try {
            $department = $this->departmentService->create($request->validated());

            ActivityLogService::record('created', "Created department \"{$department->name}\"", $department);

            return redirect()->route('admin.departments.index')
                ->with('success', 'Department created successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            $department = $this->departmentService->find($id);

            if (! $department) {
                abort(404);
            }

            $recruiters = User::whereHas('role', fn ($q) => $q->where('slug', 'recruiter'))->get();

            return view('admin.departments.edit', compact('department', 'recruiters'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(StoreDepartmentRequest $request, int $id)
    {
        try {
            $department = $this->departmentService->find($id);

            if (! $department) {
                abort(404);
            }

            $this->departmentService->update($department, $request->validated());

            ActivityLogService::record('updated', "Updated department \"{$department->name}\"", $department);

            return redirect()->route('admin.departments.index')
                ->with('success', 'Department updated successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(int $id)
    {
        try {
            $department = $this->departmentService->find($id);

            if (! $department) {
                abort(404);
            }

            $name       = $department->name;
            $this->departmentService->delete($department);

            ActivityLogService::record('deleted', "Deleted department \"{$name}\"");

            return redirect()->route('admin.departments.index')
                ->with('success', 'Department deleted successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
