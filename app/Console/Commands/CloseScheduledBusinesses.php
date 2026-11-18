<?php

namespace App\Console\Commands;

use App\Services\User\Workspaces\BusinessStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Closes each business whose scheduled closing day has come (Doc 15 section
 * 15.6), telling its owner in the app and by e-mail. Nothing is deleted.
 * Scheduled hourly.
 */
class CloseScheduledBusinesses extends Command
{
    protected $signature   = 'businesses:close-due';

    protected $description = 'Close the businesses whose scheduled closing day has come';

    public function handle(BusinessStatusService $statuses): int
    {
        $closed = $statuses->closeDue();

        $this->info('Closed '.$closed.' '.Str::plural('business', $closed).'.');

        return self::SUCCESS;
    }
}
