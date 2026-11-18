<?php

namespace App\Services\Billing;

use App\Models\Billing\Package;
use App\Models\Billing\PackageVersion;
use App\Models\User\User;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\Billing\PackageVersionRepository;
use App\Services\Shared\ActivityLogService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A plan's versions (Doc 15 section 15.8 - Phase E3b). A change to its
 * limits, branding, levels, trial or grace is a new version from a day -
 * today at once, or a later day, one waiting at a time. As a version
 * starts, the businesses subscribed to the plan stay on the version they
 * were on: existing subscribers are not moved to new limits silently. The
 * rest - Billing Starter's, and any without a subscription - have the plan's
 * terms as they stand. Versions are kept, for the businesses on them.
 */
class PlanVersions
{
    public function __construct(
        private PackageVersionRepository $versions,
        private PackageRepository $packages,
    ) {}

    /**
     * $package's versions, newest first, each with its state and how many
     * businesses are on it - the current one's counting those with the
     * plan's terms as they stand.
     *
     * @return Collection<int, array{version: PackageVersion, state: string, businesses: int}>
     */
    public function history(Package $package): Collection
    {
        $current = $this->versions->current($package);
        $plain   = $this->versions->countOnCurrent($package);

        return $this->versions->history($package)->map(fn (PackageVersion $version) => [
            'version'    => $version,
            'state'      => $state = $version->stateBeside($current),
            'businesses' => $version->held_count + ($state === PackageVersion::CURRENT ? $plain : 0),
        ])->toBase();
    }

    /**
     * $terms - the plan's own, changed - as $package's next version from
     * $from, made by $admin. Not started here: see start(). Refused while
     * another version waits for its day, whose terms would undo these.
     */
    public function add(Package $package, array $terms, CarbonInterface $from, User $admin): PackageVersion
    {
        if ($waiting = $this->versions->scheduled($package)) {
            throw ValidationException::withMessages(['effective_on' => "Version {$waiting->number} starts on {$waiting->starts_on->format('j M Y')}. Remove it, under Versions, before changing the limits, branding, levels, trial or grace again."]);
        }

        return $this->versions->create([
            ...$package->only(PackageVersion::TERMS),
            ...array_intersect_key($terms, array_flip(PackageVersion::TERMS)),
            'package_id' => $package->id,
            'number'     => $this->versions->nextNumber($package),
            'starts_on'  => $from->toDateString(),
            'created_by' => $admin->id,
        ]);
    }

    /**
     * Starts $version: the businesses subscribed to $package stay on its
     * current version, and $package takes $version's terms - with $fields,
     * the rest of the plan's form, in the same change. @return int the businesses held
     */
    public function start(PackageVersion $version, Package $package, array $fields = []): int
    {
        return DB::transaction(function () use ($version, $package, $fields) {
            $current = $this->versions->current($package);
            $held    = $current ? $this->versions->holdSubscribers($package, $current) : 0;

            $this->packages->update($package, [...$fields, ...$version->terms()]);
            $this->versions->markStarted($version);

            return $held;
        });
    }

    /** Starts every version whose day has come, oldest first (plans:advance). @return int how many */
    public function startDue(): int
    {
        $due = $this->versions->due(Carbon::today());

        foreach ($due as $version) {
            $held = $this->start($version, $version->package);

            ActivityLogService::record('updated', "Plan \"{$version->package->name}\" moved to its version {$version->number}; ".trans_choice(':count subscriber stays|:count subscribers stay', $held).' on the version before', $version->package, ['source' => 'system']);
        }

        return $due->count();
    }

    /** A version not started yet, removed: the plan's terms stay as they are. */
    public function remove(Package $package, int $versionId): PackageVersion
    {
        $version = $this->versions->find($package, $versionId) ?? abort(404);

        if ($version->applied_at !== null) {
            throw ValidationException::withMessages(['version' => "Version {$version->number} has started: businesses are on it, so it stays."]);
        }

        $this->versions->delete($version);

        return $version;
    }
}
