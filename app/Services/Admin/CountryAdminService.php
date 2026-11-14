<?php

namespace App\Services\Admin;

use App\Models\Admin\Country;
use App\Models\Admin\CountryVersion;
use App\Models\Billing\PaymentMethod;
use App\Models\User\User;
use App\Repositories\Admin\CountryRepository;
use App\Repositories\Admin\CurrencyRepository;
use App\Repositories\Admin\PaymentMethodRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\AddressFormat;
use App\Support\CountryDirectory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Countries (Doc 15 section 15.19 - Phase F3a): each country's name
 * and calling code, saved at once, and the defaults a new business there
 * takes - its currency, time zone, regional settings, and its addresses'
 * labels and format - saved as its next version, from today or a later day
 * (CountryVersions). Every change is in the audit log.
 */
class CountryAdminService
{
    public function __construct(
        protected CountryRepository $countries,
        protected CountryVersions $versions,
        protected CurrencyRepository $currencies,
        protected CountryDirectory $directory,
        protected PaymentMethodRepository $methods,
    ) {}

    public function index(?string $search): LengthAwarePaginator
    {
        return $this->countries->paginated($search);
    }

    public function findOrFail(int $id): Country
    {
        return $this->countries->findOrFail($id);
    }

    /** The country's form: the country, its versions, the businesses based there, and the choices for its defaults. */
    public function editPage(int $id): array
    {
        $country = $this->countries->findOrFail($id);
        $zones   = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $country->code);

        return [
            'country'    => $country,
            'versions'   => $this->versions->history($country),
            'businesses' => $this->countries->businessesIn($country),
            // izSuite's ways to pay offered to a business billed there (Phase F3b).
            'payments'   => $this->methods->offered(PaymentMethod::BUILT)->filter(fn (PaymentMethod $method) => $method->offeredIn($country->code))->pluck('name'),
            'currencies' => $this->currencies->allByName(),
            'zones'      => $zones ?: \DateTimeZone::listIdentifiers(),
            'example'    => AddressFormat::lines($country->address_format, [...AddressFormat::SAMPLE, 'country' => $country->name]),
        ];
    }

    /**
     * $data is validated (UpdateCountryRequest). The name and calling code
     * change at once; changed defaults are the country's next version from
     * effective_on, started now when that is today. @return string what happened
     */
    public function update(Country $country, array $data, User $admin): string
    {
        $from     = Carbon::parse($data['effective_on'] ?? 'today')->startOfDay();
        $defaults = Arr::only($data, Country::DEFAULTS);
        $fields   = Arr::only($data, ['name', 'calling_code']);
        $was      = $country->name;

        $version = DB::transaction(function () use ($country, $defaults, $fields, $from, $admin) {
            $version = CountryVersion::differ($defaults, $country) ? $this->versions->add($country, $defaults, $from, $admin) : null;

            if ($version && $from->lte(Carbon::today())) {
                $this->versions->start($version, $country, $fields);

                return $version;
            }

            $this->countries->update($country, $fields);

            return $version;
        });

        $this->directory->forget();

        $started = $version?->applied_at !== null;
        $what    = match (true) {
            $version === null => '',
            $started          => "; version {$version->number} of its defaults started",
            default           => "; version {$version->number} of its defaults starts on {$version->starts_on->format('j M Y')}",
        };

        ActivityLogService::record('updated', "Updated country \"{$was}\"{$what}", $country, $version ? ['version' => $version->number] : null, $admin);

        return match (true) {
            $version === null => 'Country saved.',
            $started          => "Saved: version {$version->number} of its defaults has started. Businesses set up from now start from it; those already set up keep their own settings.",
            default           => "Saved: version {$version->number} of its defaults starts on {$version->starts_on->format('j M Y')}. Until then they stay as they are.",
        };
    }

    /** A version waiting for its day, removed. */
    public function removeVersion(int $id, int $versionId, User $admin): string
    {
        $country = $this->countries->findOrFail($id);
        $version = $this->versions->remove($country, $versionId);

        ActivityLogService::record('deleted', "Removed version {$version->number} of country \"{$country->name}\"'s defaults, due to start on {$version->starts_on->format('j M Y')}", $country, null, $admin);

        return "Version {$version->number} is removed: the country's defaults stay as they are.";
    }
}
