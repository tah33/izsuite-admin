<?php

namespace App\Repositories\Billing;

use App\Models\Billing\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionRepository
{
    /**
     * Get paginated subscriptions for admin with optional filters.
     */
    public function getPaginatedForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Subscription::query()
            ->with(['user.role', 'latestInvoice'])
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function (Builder $builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('plan_slug', 'like', "%{$search}%")
                    ->orWhere('payment_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['billing_cycle'])) {
            $query->where('billing_cycle', $filters['billing_cycle']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate(requested_per_page($perPage))->withQueryString();
    }

    public function getDistinctStatuses(): array
    {
        return Subscription::query()
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->values()
            ->all();
    }

    public function getDistinctBillingCycles(): array
    {
        return Subscription::query()
            ->select('billing_cycle')
            ->whereNotNull('billing_cycle')
            ->distinct()
            ->orderBy('billing_cycle')
            ->pluck('billing_cycle')
            ->values()
            ->all();
    }

    /**
     * Get paginated subscriptions for a user with optional filters.
     */
    public function getForUser(int $userId, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query   = Subscription::where('user_id', $userId)
            ->with('category');

        // Filter by status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by category
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Search by name
        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        // Sort
        $sortBy  = $filters['sort_by'] ?? 'next_renewal_date';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate(requested_per_page($perPage));
    }

    /**
     * Get active count for a user.
     */
    public function getActiveCount(int $userId): int
    {
        return Subscription::where('user_id', $userId)->active()->count();
    }

    public function getActivePlanSubscription(int $userId): ?Subscription
    {
        return Subscription::where('user_id', $userId)
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('plan_id')
            ->with('category')
            ->latest('id')
            ->first();
    }

    public function getLatestBillablePlanSubscription(int $userId): ?Subscription
    {
        return Subscription::where('user_id', $userId)
            ->whereNotNull('plan_id')
            ->whereIn('status', ['active', 'trial', 'pending'])
            ->latest('id')
            ->first();
    }

    public function findByPaymentId(string $paymentId): ?Subscription
    {
        return Subscription::where('payment_id', $paymentId)->first();
    }

    public function findRecruiterById(int $subscriptionId): ?Subscription
    {
        return Subscription::with('user.role')
            ->whereKey($subscriptionId)
            ->first();
    }

    public function findPendingByPaymentId(string $paymentId): ?Subscription
    {
        return Subscription::where('payment_id', $paymentId)
            ->where('status', 'pending')
            ->first();
    }

    /**
     * Get total monthly spend for a user.
     */
    public function getMonthlySpend(int $userId): float
    {
        $subscriptions = Subscription::where('user_id', $userId)->active()->get();

        return $subscriptions->sum(fn ($sub) => $sub->monthly_cost);
    }

    /**
     * Get potential savings (low/unused subscriptions).
     */
    public function getPotentialSavings(int $userId): float
    {
        $leaks = Subscription::where('user_id', $userId)->leaks()->get();

        return $leaks->sum(fn ($sub) => $sub->monthly_cost);
    }

    /**
     * Get subscriptions needing attention (low/unused usage).
     */
    public function getNeedsAttentionCount(int $userId): int
    {
        return Subscription::where('user_id', $userId)->leaks()->count();
    }

    /**
     * Get upcoming renewals.
     */
    public function getUpcomingRenewals(int $userId, int $days = 30): Collection
    {
        return Subscription::where('user_id', $userId)
            ->upcomingRenewals($days)
            ->limit(5)
            ->get();
    }

    /**
     * Get subscription leaks (low/unused).
     */
    public function getLeaks(int $userId): Collection
    {
        return Subscription::where('user_id', $userId)
            ->leaks()
            ->with('category')
            ->get();
    }

    /**
     * Get monthly spend history for chart (last 6 months).
     */
    public function getSpendingHistory(int $userId, int $months = 6): array
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date   = now()->subMonths($i);
            $data[] = [
                'label' => $date->format('M'),
                'value' => Subscription::where('user_id', $userId)
                    ->active()
                    ->where('start_date', '<=', $date->endOfMonth())
                    ->get()
                    ->sum(fn ($sub) => $sub->monthly_cost),
            ];
        }

        return $data;
    }

    /**
     * Find a subscription by ID for a specific user.
     */
    public function findForUser(int $userId, int $subscriptionId): ?Subscription
    {
        return Subscription::where('user_id', $userId)
            ->with('category')
            ->find($subscriptionId);
    }

    public function cancelActivePlanSubscriptionsForUser(int $userId, ?int $exceptId = null): int
    {
        $query = Subscription::where('user_id', $userId)
            ->whereNotNull('plan_id')
            ->whereIn('status', ['active', 'trial']);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Create a subscription.
     */
    public function create(array $data): Subscription
    {
        return Subscription::create($data);
    }

    /**
     * Update a subscription.
     */
    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);

        return $subscription->fresh();
    }

    public function incrementUsage(Subscription $subscription, string $column): Subscription
    {
        $subscription->increment($column);

        return $subscription->fresh();
    }

    /**
     * Delete a subscription.
     */
    public function delete(Subscription $subscription): bool
    {
        return $subscription->delete();
    }
}
