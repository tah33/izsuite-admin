<?php

namespace App\Services\Admin;

use App\Models\Admin\Department;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DepartmentService
{
    public function __construct(
        protected DepartmentRepository $departmentRepository,
        protected UserRepository $userRepository,
    ) {}

    public function getFilteredDepartments(?int $recruiterId = null): LengthAwarePaginator
    {
        return $this->departmentRepository->getFilteredPaginated(['recruiter_id' => $recruiterId]);
    }

    /** The recruiters a department can belong to. */
    public function recruiters(): Collection
    {
        return $this->userRepository->allWithRole('recruiter');
    }

    public function find(int $id): ?Department
    {
        return $this->departmentRepository->find($id);
    }

    public function create(array $data): Department
    {
        $department = $this->departmentRepository->create($data);

        ActivityLogService::record('created', "Created department \"{$department->name}\"", $department);

        return $department;
    }

    public function update(Department $department, array $data): Department
    {
        $updated = $this->departmentRepository->update($department, $data);

        ActivityLogService::record('updated', "Updated department \"{$department->name}\"", $department);

        return $updated;
    }

    public function delete(Department $department): bool
    {
        $name    = $department->name;
        $deleted = $this->departmentRepository->delete($department);

        ActivityLogService::record('deleted', "Deleted department \"{$name}\"");

        return $deleted;
    }
}
