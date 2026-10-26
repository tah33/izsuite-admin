<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\Api\Auth\AccountDefaultsService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Gives a new account its "Main Location", with no country or state yet.
 *
 * Queued after commit and retried like the other UserRegistered listeners - see
 * CreateDefaultWorkspace for why.
 */
class CreateDefaultLocation implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(protected AccountDefaultsService $accountDefaults) {}

    public function handle(UserRegistered $event): void
    {
        $this->accountDefaults->createLocation($event->userId);
    }
}
