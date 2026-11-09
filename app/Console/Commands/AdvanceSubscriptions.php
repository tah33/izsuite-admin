<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionCycle;
use Illuminate\Console\Command;

/**
 * Moves every running subscription on to its business's today (Doc 13
 * Phase C2a): renewal invoices, the period's end, grace and its daily
 * e-mails, and trials ending. Scheduled hourly, so each business is seen
 * soon after its own midnight; each step happens once (SubscriptionCycle).
 */
class AdvanceSubscriptions extends Command
{
    protected $signature   = 'subscriptions:advance';

    protected $description = 'Renew, remind, start grace and end subscriptions as their days come';

    public function handle(SubscriptionCycle $cycle): int
    {
        $this->info("Moved on {$cycle->run()} subscriptions.");

        return self::SUCCESS;
    }
}
