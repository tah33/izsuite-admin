<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Country;
use App\Models\User\BusinessSetting;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Schema;

/** The countries (Doc 15 Phase F3a), each with the defaults a new business there takes. */
class CountryRepository
{
    /** Every country as Countries reads it - each a plain array, its defaults cast. @return list<array<string, mixed>> */
    public function directory(): array
    {
        return Country::query()->get()->map(fn (Country $country) => $country->only(['code', 'name', 'calling_code', ...Country::DEFAULTS]))->all();
    }

    /** Whether the countries table is there yet: not before migration 000145. */
    public function tableExists(): bool
    {
        return Schema::hasTable('countries');
    }

    /** A page of the countries by name, $search in a name or code, each with how many businesses are based there and its version waiting, if one is. */
    public function paginated(?string $search, int $perPage = 50): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(Country::query())
            ->through([new SearchFilter($search, columns: ['name', 'code'])])
            ->thenReturn()
            ->addSelect(['countries.*', 'businesses_count' => BusinessSetting::query()->selectRaw('count(*)')->whereColumn('business_settings.country', 'countries.code')])
            ->with(['versions' => fn ($versions) => $versions->whereNull('applied_at')->select(['id', 'country_id', 'number', 'starts_on', 'applied_at'])])
            ->orderBy('name')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function findOrFail(int $id): Country
    {
        return Country::findOrFail($id);
    }

    /** How many businesses are based in $country, by their profile. */
    public function businessesIn(Country $country): int
    {
        return BusinessSetting::query()->where('country', $country->code)->count();
    }

    public function update(Country $country, array $data): Country
    {
        $country->update($data);

        return $country;
    }
}
