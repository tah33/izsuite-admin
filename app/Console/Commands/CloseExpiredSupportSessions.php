<?php

namespace App\Console\Commands;

use App\Services\Shared\SupportSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Ends each support session past its end (Doc 15 section 15.22): its tokens
 * stopped working at that time already; this records the end in the
 * business's Activity Log. Scheduled every five minutes.
 */
class CloseExpiredSupportSessions extends Command
{
    protected $signature   = 'support-sessions:close-expired';

    protected $description = 'End the support sessions past their end';

    public function handle(SupportSessionService $sessions): int
    {
        $ended = $sessions->closeExpired();

        $this->info('Ended '.$ended.' '.Str::plural('support session', $ended).'.');

        return self::SUCCESS;
    }
}
