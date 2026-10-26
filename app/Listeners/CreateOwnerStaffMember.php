<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\Api\Auth\AccountDefaultsService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Adds a new account's owner as the first of their own staff, assigned to the
 * "Billing Starter" workspace if that is already there (see
 * AccountDefaultsService).
 *
 * Queued after commit and retried like the other UserRegistered listeners - see
 * CreateDefaultWorkspace for why.
 */
class CreateOwnerStaffMember implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(protected AccountDefaultsService $accountDefaults) {}

    public function handle(UserRegistered $event): void
    {
        $this->accountDefaults->createOwnerStaffMember($event->userId);
    }
}
