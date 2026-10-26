<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
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
            $recruiters  = $this->departmentService->recruiters();

            return view('admin.departments.index', compact('departments', 'recruiters'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            $recruiters = $this->departmentService->recruiters();

            return view('admin.departments.create', compact('recruiters'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreDepartmentRequest $request)
    {
        try {
            $this->departmentService->create($request->validated());

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

            $recruiters = $this->departmentService->recruiters();

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

            $this->departmentService->delete($department);

            return redirect()->route('admin.departments.index')
                ->with('success', 'Department deleted successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
