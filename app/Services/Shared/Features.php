<?php

namespace App\Services\Shared;

use App\Models\Admin\FeatureFlag;
use App\Models\User\Workspace;
use App\Repositories\Admin\FeatureFlagRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Whether a feature is on (Doc 15 section 15.12 - Phase E1), as the code
 * asks: Features::enabled('new_reports', $workspace). A flag switched on is
 * on, between its days, for the businesses it names, and for those in its
 * countries and plans (none chosen: all) - a share of them, the same ones
 * each time. A flag not made, or off, is off. The app gets a business's
 * features with its navigation.
 */
class Features
{
    /** The flags switched on, kept a few minutes; a change in the admin forgets them. */
    public const CACHE_KEY = 'feature-flags:on';

    public const CACHE_SECONDS = 300;

    public function __construct(
        private FeatureFlagRepository $flags,
    ) {}

    /** Whether feature $key is on for $workspace - or, without one, for every business. */
    public static function enabled(string $key, ?Workspace $workspace = null): bool
    {
        return app(self::class)->isOn($key, $workspace);
    }

    public function isOn(string $key, ?Workspace $workspace = null): bool
    {
        $flag = $this->switchedOn()->firstWhere('key', $key);

        return $flag !== null && $this->reaches($flag, $workspace);
    }

    /** @return list<string> the keys of the features on for $workspace */
    public function for(Workspace $workspace): array
    {
        return $this->switchedOn()->filter(fn (FeatureFlag $flag) => $this->reaches($flag, $workspace))->pluck('key')->sort()->values()->all();
    }

    /** Forgets the flags kept, so a change counts at once. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return Collection<int, FeatureFlag> */
    private function switchedOn(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->flags->switchedOn());
    }

    private function reaches(FeatureFlag $flag, ?Workspace $workspace): bool
    {
        if ($flag->stateAt(now()) !== 'live') {
            return false;
        }

        // Without a business, only a flag on for every business is on.
        if ($workspace === null) {
            return empty($flag->countries) && empty($flag->packages) && $flag->percentage >= 100;
        }

        if (in_array($workspace->id, array_map('intval', $flag->businesses ?? []), true)) {
            return true;
        }

        return $flag->percentage > 0
            && (empty($flag->packages) || in_array($workspace->package?->key, $flag->packages, true))
            && (empty($flag->countries) || in_array($this->flags->countryOf($workspace), $flag->countries, true))
            && ($flag->percentage >= 100 || self::bucket($flag->key, $workspace->id) < $flag->percentage);
    }

    /** Where $workspaceId falls, 0 to 99, for flag $key: the same each time, and apart for each flag. */
    public static function bucket(string $key, int $workspaceId): int
    {
        return crc32("{$key}:{$workspaceId}") % 100;
    }
}
