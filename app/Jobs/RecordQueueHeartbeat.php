<?php

namespace App\Jobs;

use App\Jobs\Concerns\SafeToRetry;
use App\Services\Admin\Health\Heartbeats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Notes that a queue worker ran a job just now (Doc 15 Phase D3a), for
 * Admin > Service Health. Queued each minute by health:heartbeat; one waits
 * at a time, so a stopped worker finds no pile of them.
 */
class RecordQueueHeartbeat implements SafeToRetry, ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Seconds one waiting heartbeat keeps others out. */
    public int $uniqueFor = 600;

    public function handle(Heartbeats $heartbeats): void
    {
        $heartbeats->beat(Heartbeats::QUEUE);
    }
}
