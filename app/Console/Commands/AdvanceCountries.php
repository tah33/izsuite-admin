<?php

namespace App\Console\Commands;

use App\Services\Admin\CountryVersions;
use Illuminate\Console\Command;

/**
 * Starts each version of a country's defaults whose day has come (Doc 15
 * Phase F3a): the country takes them, for the businesses set up from then.
 * Scheduled hourly; each version starts once.
 */
class AdvanceCountries extends Command
{
    protected $signature   = 'countries:advance';

    protected $description = 'Start the versions of country defaults whose day has come';

    public function handle(CountryVersions $versions): int
    {
        $this->info('Started '.trans_choice(':count country version|:count country versions', $versions->startDue()).'.');

        return self::SUCCESS;
    }
}
