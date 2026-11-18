<?php

namespace App\Console\Commands;

use App\Jobs\RecordQueueHeartbeat;
use App\Services\Admin\Health\Heartbeats;
use Illuminate\Console\Command;

/**
 * The scheduler's heartbeat, and a queued one for the workers (Doc 15 Phase
 * D3a): Admin > Service Health tells from them whether both are running.
 * Scheduled each minute.
 */
class RecordHeartbeat extends Command
{
    protected $signature   = 'health:heartbeat';

    protected $description = 'Note that the scheduler runs, and queue one for the workers';

    public function handle(Heartbeats $heartbeats): int
    {
        $heartbeats->beat(Heartbeats::SCHEDULER);
        RecordQueueHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
