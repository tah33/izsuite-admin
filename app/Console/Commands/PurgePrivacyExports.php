<?php

namespace App\Console\Commands;

use App\Services\Admin\Privacy\PrivacyRequestService;
use Illuminate\Console\Command;

/**
 * Deletes each privacy request's data export whose days are over (Doc 15
 * section 15.24 - Phase G1: "sensitive exported files expire after a
 * configured period"). The request keeps saying what was sent. Hourly.
 */
class PurgePrivacyExports extends Command
{
    protected $signature   = 'privacy:purge-exports';

    protected $description = 'Delete the data exports whose link has expired';

    public function handle(PrivacyRequestService $requests): int
    {
        $this->info('Deleted '.trans_choice(':count expired export|:count expired exports', $requests->purgeExpired()).'.');

        return self::SUCCESS;
    }
}
