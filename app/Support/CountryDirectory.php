<?php

namespace App\Support;

use App\Repositories\Admin\CountryRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * The countries as every request reads them (Doc 15 Phase F3a): the
 * countries table, arranged by name (Countries::arrange), kept in the cache
 * until an admin changes a country or a version of its defaults starts - and
 * for the rest of the request. One per request: a singleton.
 *
 * Before the table exists - the migrations before 000145, which turned typed
 * countries into codes - the built-in list.
 */
final class CountryDirectory
{
    public const CACHE_KEY = 'countries:directory';

    /** @var ?array<string, array<string, mixed>> */
    private ?array $rows = null;

    public function __construct(
        private CountryRepository $countries,
    ) {}

    /** @return array<string, array<string, mixed>> code => the country */
    public function rows(): array
    {
        return $this->rows ??= $this->load();
    }

    /** Read afresh: after a country is changed. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);

        $this->rows = null;
    }

    private function load(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => Countries::arrange($this->countries->directory()));
        } catch (QueryException $e) {
            if ($this->countries->tableExists()) {
                throw $e;
            }

            return Countries::arrange(Countries::builtIn());
        }
    }
}
