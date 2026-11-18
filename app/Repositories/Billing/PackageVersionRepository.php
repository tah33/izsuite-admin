<?php

namespace App\Repositories\Billing;

use App\Models\Billing\Package;
use App\Models\Billing\PackageVersion;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** A plan's versions (Doc 15 Phase E3b), and the businesses held to them. */
class PackageVersionRepository
{
    /** The subscriptions whose businesses stay on their version when a newer one starts. */
    private const HELD = [WorkspaceSubscription::TRIALING, WorkspaceSubscription::ACTIVE, WorkspaceSubscription::GRACE];

    /** $package's versions, newest first, each with who made it and how many businesses stay on it. */
    public function history(Package $package): Collection
    {
        return $package->versions()->with('creator:id,first_name,last_name')->withCount('workspaces as held_count')->orderByDesc('number')->get();
    }

    /** How many businesses on $package have its terms as they stand: held to no version. */
    public function countOnCurrent(Package $package): int
    {
        return Workspace::query()->where('package_id', $package->id)->whereNull('package_version_id')->count();
    }

    /** The version $package's terms are now: the newest started. */
    public function current(Package $package): ?PackageVersion
    {
        return $package->versions()->whereNotNull('applied_at')->orderByDesc('number')->first();
    }

    /** $package's version waiting for its day, if one is. */
    public function scheduled(Package $package): ?PackageVersion
    {
        return $package->versions()->whereNull('applied_at')->orderBy('number')->first();
    }

    public function find(Package $package, int $id): ?PackageVersion
    {
        return $package->versions()->find($id);
    }

    /** The number $package's next version takes. */
    public function nextNumber(Package $package): int
    {
        return (int) $package->versions()->max('number') + 1;
    }

    public function create(array $data): PackageVersion
    {
        return PackageVersion::create($data);
    }

    public function markStarted(PackageVersion $version): void
    {
        $version->update(['applied_at' => now()]);
    }

    public function delete(PackageVersion $version): void
    {
        $version->delete();
    }

    /** The versions not started whose day has come, oldest first. */
    public function due(CarbonInterface $today): Collection
    {
        return PackageVersion::query()->whereNull('applied_at')->whereDate('starts_on', '<=', $today->toDateString())->with('package')->orderBy('starts_on')->orderBy('number')->get();
    }

    /**
     * Holds the businesses subscribed to $package - on a trial, paid, or in
     * grace - that have its terms as they stand to $version, before newer
     * terms start. @return int how many
     */
    public function holdSubscribers(Package $package, PackageVersion $version): int
    {
        return Workspace::query()
            ->where('package_id', $package->id)
            ->whereNull('package_version_id')
            ->whereHas('subscription', fn (Builder $subscription) => $subscription->where('package_id', $package->id)->whereIn('status', self::HELD))
            ->update(['package_version_id' => $version->id]);
    }
}
