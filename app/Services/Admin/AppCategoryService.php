<?php

namespace App\Services\Admin;

use App\Models\Frontend\AppCategory;
use App\Repositories\Admin\AppCategoryRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AppCategoryService
{
    public function __construct(
        protected AppCategoryRepository $appCategoryRepository,
    ) {}

    /**
     * Get filtered, paginated app categories list.
     */
    public function getFilteredCategories(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->appCategoryRepository->getPaginated($search, $perPage);
    }

    /**
     * Find app category by ID.
     */
    public function find(int $id): ?AppCategory
    {
        return $this->appCategoryRepository->find($id);
    }

    /**
     * Create a new app category.
     */
    public function create(array $data): AppCategory
    {
        $appCategory = $this->appCategoryRepository->create($data);

        ActivityLogService::record('created', "Created app category \"{$appCategory->name}\"", $appCategory);

        return $appCategory;
    }

    /**
     * Update an app category.
     */
    public function update(AppCategory $appCategory, array $data): AppCategory
    {
        $updated = $this->appCategoryRepository->update($appCategory, $data);

        ActivityLogService::record('updated', "Updated app category \"{$appCategory->name}\"", $appCategory);

        return $updated;
    }

    /**
     * Toggle the active state of an app category.
     */
    public function toggleActive(AppCategory $appCategory): AppCategory
    {
        $appCategory = $this->appCategoryRepository->toggleActive($appCategory);
        $state       = $appCategory->is_active ? 'activated' : 'deactivated';

        ActivityLogService::record('updated', "Toggled app category \"{$appCategory->name}\" to {$state}", $appCategory);

        return $appCategory;
    }

    /**
     * Delete an app category.
     */
    public function delete(AppCategory $appCategory): ?bool
    {
        $name    = $appCategory->name;
        $deleted = $this->appCategoryRepository->delete($appCategory);

        ActivityLogService::record('deleted', "Deleted app category \"{$name}\"");

        return $deleted;
    }

    /**
     * Why the category cannot be deleted, if it cannot: its apps would be left
     * without a group in the Apps Center.
     */
    public function deletionProblem(AppCategory $appCategory): ?string
    {
        return $this->hasApps($appCategory)
            ? 'This category still has apps. Move them, or set the category inactive instead.'
            : null;
    }

    /**
     * Whether any app sits in the category.
     */
    public function hasApps(AppCategory $appCategory): bool
    {
        return $this->appCategoryRepository->hasApps($appCategory);
    }
}
