<?php

namespace App\Services\Admin\Health;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * When the scheduler last ran, and a queue worker last ran a job (Doc 15
 * Phase D3a): health:heartbeat beats for the scheduler each minute and
 * queues RecordQueueHeartbeat, which beats for the worker. Kept in the
 * cache: clearing it forgets them until the next minute.
 */
class Heartbeats
{
    public const SCHEDULER = 'scheduler';

    public const QUEUE     = 'queue';

    public function beat(string $what): void
    {
        Cache::forever("health:heartbeat:{$what}", now()->toIso8601String());
    }

    public function last(string $what): ?CarbonImmutable
    {
        $at = Cache::get("health:heartbeat:{$what}");

        return $at ? CarbonImmutable::parse($at) : null;
    }
}
