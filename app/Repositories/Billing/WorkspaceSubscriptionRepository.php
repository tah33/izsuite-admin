<?php

namespace App\Repositories\Billing;

use App\Models\Billing\Package;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceSubscriptionRepository
{
    /** The business's subscription, with its plan and any it moves to - null if it never had one. */
    public function for(Workspace $workspace): ?WorkspaceSubscription
    {
        return $workspace->subscription()->with(['package', 'scheduledPackage'])->first();
    }

    /** Makes or changes the business's one subscription. */
    public function save(Workspace $workspace, array $data): WorkspaceSubscription
    {
        return $workspace->subscription()->updateOrCreate([], $data)->load(['package', 'scheduledPackage']);
    }

    /** Every subscription the daily cycle moves on - on a trial, active or in grace - with its business, owner and plans. */
    public function running(): Collection
    {
        return WorkspaceSubscription::with(['workspace.user', 'package', 'scheduledPackage'])->whereIn('status', WorkspaceSubscription::RUNNING)->orderBy('id')->get();
    }

    /**
     * The subscriptions on $package's $cycle in $currency that renew on it -
     * current, not ending, no move waiting - paying other than $amount, and
     * not moving to it already (Doc 15 Phase C3a): with their businesses.
     */
    public function payingOther(Package $package, string $currency, string $cycle, string $amount): Collection
    {
        return $this->renewingOn($package)
            ->with('workspace.user')
            ->where('currency', $currency)
            ->where('cycle', $cycle)
            ->where('price', '!=', $amount)
            ->where(fn (Builder $query) => $query->whereNull('next_price')->orWhere('next_price', '!=', $amount))
            ->get();
    }

    /** @return Collection<int, WorkspaceSubscription> how many renew on $package at each price - and at each new one applied to them: currency, cycle, price, next_price and count */
    public function pricesPaid(Package $package): Collection
    {
        return $this->renewingOn($package)
            ->selectRaw('currency, cycle, price, next_price, count(*) as subscriptions')
            ->whereNotNull('price')
            ->groupBy('currency', 'cycle', 'price', 'next_price')
            ->get();
    }

    /** Current subscriptions on $package that renew on it: not ending, with no move to another plan waiting. */
    private function renewingOn(Package $package): Builder
    {
        return WorkspaceSubscription::query()
            ->where('package_id', $package->id)
            ->whereIn('status', WorkspaceSubscription::CURRENT)
            ->where('cancels_at_period_end', false)
            ->whereNull('scheduled_package_id');
    }
}
