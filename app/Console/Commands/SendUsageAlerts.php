<?php

namespace App\Console\Commands;

use App\Services\Shared\Notifications\UsageAlerts;
use Illuminate\Console\Command;

/**
 * Tells those who manage each business's subscription when its plan's limits
 * are nearly reached, reached or passed (Doc 14 Phase B2). Scheduled hourly;
 * each limit's state reaches a user once (UsageAlerts).
 */
class SendUsageAlerts extends Command
{
    protected $signature   = 'notifications:usage';

    protected $description = "Notify businesses whose plan's limits are nearly reached, reached or passed";

    public function handle(UsageAlerts $alerts): int
    {
        $this->info("Sent {$alerts->run()} usage notifications.");

        return self::SUCCESS;
    }
}
