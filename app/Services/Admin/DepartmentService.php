<?php

namespace App\Services\Admin;

use App\Models\Admin\Department;
use App\Repositories\Admin\DepartmentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DepartmentService
{
    public function __construct(
        protected DepartmentRepository $departmentRepository
    ) {}

    public function getFilteredDepartments(?int $recruiterId = null): LengthAwarePaginator
    {
        return $this->departmentRepository->getFilteredPaginated(['recruiter_id' => $recruiterId]);
    }

    public function find(int $id): ?Department
    {
        return $this->departmentRepository->find($id);
    }

    public function create(array $data): Department
    {
        return $this->departmentRepository->create($data);
    }

    public function update(Department $department, array $data): Department
    {
        return $this->departmentRepository->update($department, $data);
    }

    public function delete(Department $department): bool
    {
        return $this->departmentRepository->delete($department);
    }
}
