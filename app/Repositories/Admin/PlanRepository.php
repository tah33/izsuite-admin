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
     * Public listing: active plans only, optionally filtered.
     *
     * Ordered via the model's ordered() scope (sort_order, then id) so the API
     * hands back the same sequence the admin panel shows.
     */
    public function getActivePaginated(
        ?string $search = null,
        ?string $billingType = null,
        ?string $planFor = null,
        ?bool $featured = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Plan::query()->active();

        if (! empty($billingType)) {
            $query->where('billing_type', $billingType);
        }

        if (! empty($planFor)) {
            $query->where('plan_for', $planFor);
        }

        if ($featured !== null) {
            $query->where('is_featured', $featured);
        }

        return app(Pipeline::class)
            ->send($query)
            ->through([
                new SearchFilter($search, columns: ['name', 'description']),
            ])
            ->thenReturn()
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get all active plans (for dropdowns / frontend).
     */
    public function getActive(): Collection
    {
        return Plan::active()->ordered()->get();
    }

    /** An active plan; any other id is a 404. */
    public function findActiveOrFail(int $id): Plan
    {
        return Plan::query()->where('is_active', true)->findOrFail($id);
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

    /**
     * Remove every payment provider entry of a plan.
     */
    public function deletePaymentProviders(Plan $plan): void
    {
        $plan->paymentProviders()->delete();
    }

    /**
     * Add a payment provider entry to a plan.
     */
    public function addPaymentProvider(Plan $plan, array $entry): void
    {
        $plan->paymentProviders()->create($entry);
    }
}
