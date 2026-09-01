<?php

namespace App\Services\Api\AppCategories;

use App\Repositories\Admin\AppCategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AppCategoryService
{
    public function __construct(
        protected AppCategoryRepository $appCategoryRepository,
    ) {}

    /**
     * Public, paginated list of active app categories.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->appCategoryRepository->getActivePaginated(
            $filters['search'] ?? null,
            (int) ($filters['per_page'] ?? 15),
        );
    }
}
