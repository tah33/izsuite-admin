<?php

namespace App\Console\Commands;

use App\Services\Admin\UsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Keeps each business's usage today - users, active locations, storage and
 * apps, with its plan's limits - for Admin > Usage's history (Doc 15 Phase
 * D2). Scheduled daily; run again the same day, it overwrites that day's.
 */
class SnapshotUsage extends Command
{
    protected $signature   = 'usage:snapshot';

    protected $description = "Keep each business's usage today, for its history";

    public function handle(UsageService $usage): int
    {
        $kept = $usage->snapshot();

        $this->info("Kept the usage of {$kept} ".Str::plural('business', $kept).' for '.now('UTC')->toDateString().'.');

        return self::SUCCESS;
    }
}
