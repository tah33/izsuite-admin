<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\Api\Auth\AccountDefaultsService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Gives a new account its "Billing Starter" workspace, and assigns the owner to
 * it if their staff record is already there (see AccountDefaultsService).
 *
 * Queued, like every UserRegistered listener, so registration answers without
 * waiting for it - and only once the new account is committed, so the worker
 * never looks for a user that is not saved yet. Found by Laravel's listener
 * discovery, which reads the event from handle(): registering it by hand as
 * well would queue it twice.
 */
class CreateDefaultWorkspace implements ShouldQueueAfterCommit
{
    // A failure is tried again, a little later each time, before the job is
    // given up and left in failed_jobs. Running it again is safe: nothing is
    // made twice.
    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(protected AccountDefaultsService $accountDefaults) {}

    public function handle(UserRegistered $event): void
    {
        $this->accountDefaults->createWorkspace($event->userId);
    }
}
