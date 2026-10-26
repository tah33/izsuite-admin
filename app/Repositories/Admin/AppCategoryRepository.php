<?php

namespace App\Repositories\Admin;

use App\Models\Frontend\AppCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class AppCategoryRepository
{
    /**
     * Get filtered, paginated app categories list.
     */
    public function getPaginated(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = AppCategory::query()->withCount('apps');

        if (! empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        // The Apps Center's order.
        return $query->orderBy('sort_order')->orderBy('name')->paginate($perPage)->withQueryString();
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

    /** Active categories, in the Apps Center's order: id => name. */
    public function activeNames(): Collection
    {
        return AppCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id');
    }

    /**
     * Every category with its apps, both in the Apps Center's order - a
     * family's Lite edition before its Full one.
     */
    public function allWithApps(): EloquentCollection
    {
        return AppCategory::with(['apps' => fn ($q) => $q->orderBy('sort_order')->orderBy('family_key')
            ->orderByRaw("case when edition = 'lite' then 1 else 2 end")])
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Active categories, in the Apps Center's order. */
    public function activeInOrder(): EloquentCollection
    {
        return AppCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Whether any app is filed under $appCategory. */
    public function hasApps(AppCategory $appCategory): bool
    {
        return $appCategory->apps()->exists();
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
