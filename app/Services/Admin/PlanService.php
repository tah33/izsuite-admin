<?php

namespace App\Services\Admin;

use App\Models\Billing\Plan;
use App\Repositories\Admin\PlanRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PlanService
{
    public function __construct(
        protected PlanRepository $planRepo,
    ) {}

    /**
     * Get paginated plans.
     */
    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->planRepo->getPaginated($search, $perPage);
    }

    /**
     * Get active plans.
     */
    public function getActive(): Collection
    {
        return $this->planRepo->getActive();
    }

    /**
     * Find a plan by ID.
     */
    public function find(int $id): ?Plan
    {
        return $this->planRepo->find($id);
    }

    /**
     * Create a plan.
     */
    public function create(array $data): Plan
    {
        return $this->planRepo->create($data);
    }

    /**
     * Update a plan.
     */
    public function update(Plan $plan, array $data): Plan
    {
        return $this->planRepo->update($plan, $data);
    }

    /**
     * Delete a plan.
     */
    public function delete(Plan $plan): bool
    {
        return $this->planRepo->delete($plan);
    }

    /**
     * Sync payment provider entries for a plan.
     */
    public function syncPaymentProviders(Plan $plan, array $providers): void
    {
        $plan->paymentProviders()->delete();

        foreach ($providers as $entry) {
            if (filled($entry['provider_price_id'] ?? null)) {
                $plan->paymentProviders()->create($entry);
            }
        }
    }
}
