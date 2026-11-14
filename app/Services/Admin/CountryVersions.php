<?php

namespace App\Services\Admin;

use App\Models\Admin\Country;
use App\Models\Admin\CountryVersion;
use App\Models\User\User;
use App\Repositories\Admin\CountryRepository;
use App\Repositories\Admin\CountryVersionRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\CountryDirectory;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The versions of a country's defaults (Doc 15 section 15.19 - Phase F3a):
 * "localization changes use effective dates". A change to the currency, time
 * zone, regional settings or address format a new business there takes is
 * the country's next version - from today, or a later day, one waiting at a
 * time. When one starts, the country takes it: businesses set up from then
 * start from it, and the address format prints on documents issued from
 * then. Businesses already set up keep their own settings; documents already
 * issued keep what they printed.
 */
class CountryVersions
{
    public function __construct(
        private CountryVersionRepository $versions,
        private CountryRepository $countries,
        private CountryDirectory $directory,
    ) {}

    /** @return Collection<int, array{version: CountryVersion, state: string}> $country's versions, newest first */
    public function history(Country $country): Collection
    {
        $current = $this->versions->current($country);

        return $this->versions->history($country)->map(fn (CountryVersion $version) => [
            'version' => $version,
            'state'   => $version->stateBeside($current),
        ])->toBase();
    }

    /**
     * $defaults - the country's own, changed - as its next version from
     * $from, made by $admin. Not started here: see start(). Refused while
     * another waits for its day, whose defaults would undo these.
     */
    public function add(Country $country, array $defaults, CarbonInterface $from, User $admin): CountryVersion
    {
        if ($waiting = $this->versions->scheduled($country)) {
            throw ValidationException::withMessages(['effective_on' => "Version {$waiting->number} starts on {$waiting->starts_on->format('j M Y')}. Remove it, under Versions, before changing the defaults again."]);
        }

        return $this->versions->create([
            ...$country->only(Country::DEFAULTS),
            ...array_intersect_key($defaults, array_flip(Country::DEFAULTS)),
            'country_id' => $country->id,
            'number'     => $this->versions->nextNumber($country),
            'starts_on'  => $from->toDateString(),
            'created_by' => $admin->id,
        ]);
    }

    /** Starts $version: $country takes its defaults - with $fields, the rest of the country's form, in the same change. */
    public function start(CountryVersion $version, Country $country, array $fields = []): void
    {
        DB::transaction(function () use ($version, $country, $fields) {
            $this->countries->update($country, [...$fields, ...$version->defaults()]);
            $this->versions->markStarted($version);
        });

        $this->directory->forget();
    }

    /** Starts every version whose day has come, oldest first (countries:advance). @return int how many */
    public function startDue(): int
    {
        $due = $this->versions->due(Carbon::today());

        foreach ($due as $version) {
            $this->start($version, $version->country);

            ActivityLogService::record('updated', "Country \"{$version->country->name}\" moved to version {$version->number} of its defaults", $version->country, ['source' => 'system']);
        }

        return $due->count();
    }

    /** A version not started yet, removed: the country's defaults stay as they are. */
    public function remove(Country $country, int $versionId): CountryVersion
    {
        $version = $this->versions->find($country, $versionId) ?? abort(404);

        if ($version->applied_at !== null) {
            throw ValidationException::withMessages(['version' => "Version {$version->number} has started, so it stays with the country's history."]);
        }

        $this->versions->delete($version);

        return $version;
    }
}
