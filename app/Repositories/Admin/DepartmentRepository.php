<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DepartmentRepository
{
    public function getFilteredPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Department::query()->with('creator');

        if (! empty($filters['recruiter_id'])) {
            $query->where('created_by', $filters['recruiter_id']);
        }

        return $query->latest()->paginate(requested_per_page($perPage));
    }

    public function getByRecruiter(int $recruiterId): Collection
    {
        return Department::where('created_by', $recruiterId)->get();
    }

    public function find(int $id): ?Department
    {
        return Department::find($id);
    }

    public function findByRecruiter(int $id, int $recruiterId): ?Department
    {
        return Department::where('id', $id)
            ->where('created_by', $recruiterId)
            ->first();
    }

    public function create(array $data): Department
    {
        return Department::create($data);
    }

    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department->fresh();
    }

    public function delete(Department $department): bool
    {
        return $department->delete();
    }
}
