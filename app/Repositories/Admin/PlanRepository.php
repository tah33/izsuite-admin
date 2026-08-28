<?php

namespace App\Repositories\Admin;

use App\Models\Billing\Plan;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;

class PlanRepository
{
    /**
     * Get paginated plans with optional search.
     */
    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(Plan::query()->withCount('subscriptions'))
            ->through([
                new SearchFilter($search, columns: ['name', 'description']),
            ])
            ->thenReturn()
            ->ordered()
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /**
     * Get all active plans (for dropdowns / frontend).
     */
    public function getActive(): Collection
    {
        return Plan::active()->ordered()->get();
    }

    /**
     * Find a plan by ID with related data.
     */
    public function find(int $id): ?Plan
    {
        return Plan::withCount('subscriptions')
            ->with('paymentProviders')
            ->find($id);
    }

    /**
     * Create a new plan.
     */
    public function create(array $data): Plan
    {
        return Plan::create($data);
    }

    /**
     * Update a plan.
     */
    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan->fresh();
    }

    /**
     * Delete a plan.
     */
    public function delete(Plan $plan): bool
    {
        return $plan->delete();
    }
}
