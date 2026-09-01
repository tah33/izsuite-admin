<?php

namespace App\Services\Api\Apps;

use App\Repositories\Admin\AppRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AppService
{
    public function __construct(
        protected AppRepository $appRepository,
    ) {}

    /**
     * Public, paginated list of active apps.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->appRepository->getActivePaginated(
            $filters['search'] ?? null,
            $filters['category'] ?? null,
            $filters['status'] ?? null,
            (int) ($filters['per_page'] ?? 15),
        );
    }
}
