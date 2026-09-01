<?php

namespace App\Services\Api\Plans;

use App\Repositories\Admin\PlanRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlanService
{
    public function __construct(
        protected PlanRepository $planRepository,
    ) {}

    /**
     * Public, paginated list of active plans.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->planRepository->getActivePaginated(
            $filters['search'] ?? null,
            $filters['billing_type'] ?? null,
            $filters['plan_for'] ?? null,
            $filters['featured'] ?? null,
            (int) ($filters['per_page'] ?? 15),
        );
    }
}
