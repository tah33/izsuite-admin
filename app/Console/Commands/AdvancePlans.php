<?php

namespace App\Console\Commands;

use App\Services\Billing\PlanVersions;
use Illuminate\Console\Command;

/**
 * Starts each plan version whose day has come (Doc 15 Phase E3b): the
 * plan takes its terms, and the businesses subscribed to it stay on the
 * version they were on. Scheduled hourly; each version starts once.
 */
class AdvancePlans extends Command
{
    protected $signature   = 'plans:advance';

    protected $description = 'Start the plan versions whose day has come';

    public function handle(PlanVersions $versions): int
    {
        $this->info('Started '.trans_choice(':count plan version|:count plan versions', $versions->startDue()).'.');

        return self::SUCCESS;
    }
}
