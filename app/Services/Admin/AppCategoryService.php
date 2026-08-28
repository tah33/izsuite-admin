<?php

namespace App\Services\Admin;

use App\Models\Frontend\AppCategory;
use App\Repositories\Admin\AppCategoryRepository;
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
        return $this->appCategoryRepository->create($data);
    }

    /**
     * Update an app category.
     */
    public function update(AppCategory $appCategory, array $data): AppCategory
    {
        return $this->appCategoryRepository->update($appCategory, $data);
    }

    /**
     * Toggle the active state of an app category.
     */
    public function toggleActive(AppCategory $appCategory): AppCategory
    {
        return $this->appCategoryRepository->toggleActive($appCategory);
    }

    /**
     * Delete an app category.
     */
    public function delete(AppCategory $appCategory): ?bool
    {
        return $this->appCategoryRepository->delete($appCategory);
    }
}
