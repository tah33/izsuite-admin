<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Country;
use App\Models\Admin\CountryVersion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/** The versions of a country's defaults (Doc 15 Phase F3a). */
class CountryVersionRepository
{
    /** $country's versions, newest first, each with who made it. */
    public function history(Country $country): Collection
    {
        return $country->versions()->with('creator:id,first_name,last_name')->orderByDesc('number')->get();
    }

    /** The version $country's defaults are now: the newest started. */
    public function current(Country $country): ?CountryVersion
    {
        return $country->versions()->whereNotNull('applied_at')->orderByDesc('number')->first();
    }

    /** $country's version waiting for its day, if one is. */
    public function scheduled(Country $country): ?CountryVersion
    {
        return $country->versions()->whereNull('applied_at')->orderBy('number')->first();
    }

    public function find(Country $country, int $id): ?CountryVersion
    {
        return $country->versions()->find($id);
    }

    /** The number $country's next version takes. */
    public function nextNumber(Country $country): int
    {
        return (int) $country->versions()->max('number') + 1;
    }

    public function create(array $data): CountryVersion
    {
        return CountryVersion::create($data);
    }

    public function markStarted(CountryVersion $version): void
    {
        $version->update(['applied_at' => now()]);
    }

    public function delete(CountryVersion $version): void
    {
        $version->delete();
    }

    /** The versions not started whose day has come, oldest first. */
    public function due(CarbonInterface $today): Collection
    {
        return CountryVersion::query()->whereNull('applied_at')->whereDate('starts_on', '<=', $today->toDateString())->with('country')->orderBy('starts_on')->orderBy('number')->get();
    }
}
