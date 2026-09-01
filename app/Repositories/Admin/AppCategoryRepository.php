<?php

namespace App\Repositories\Admin;

use App\Models\Frontend\AppCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AppCategoryRepository
{
    /**
     * Get filtered, paginated app categories list.
     */
    public function getPaginated(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = AppCategory::query();

        if (! empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * Public listing: active categories only, optionally filtered.
     */
    public function getActivePaginated(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = AppCategory::query()->where('is_active', true);

        if (! empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?AppCategory
    {
        return AppCategory::find($id);
    }

    public function create(array $data): AppCategory
    {
        return AppCategory::create($data);
    }

    public function update(AppCategory $appCategory, array $data): AppCategory
    {
        $appCategory->update($data);

        return $appCategory->fresh();
    }

    public function toggleActive(AppCategory $appCategory): AppCategory
    {
        $appCategory->is_active = ! $appCategory->is_active;
        $appCategory->save();

        return $appCategory;
    }

    public function delete(AppCategory $appCategory): ?bool
    {
        return $appCategory->delete();
    }
}
