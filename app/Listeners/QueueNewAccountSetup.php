<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Jobs\AddOwnerAsStaff;
use App\Jobs\CreateMainLocation;
use App\Jobs\CreateStarterWorkspace;
use Illuminate\Support\Facades\Bus;

/**
 * Queues what a new account starts with: the "Billing Starter" workspace, the
 * "Main Location", and the owner as a staff member assigned to that workspace.
 *
 * Only queues - registration does not wait for any of it.
 *
 * Found by event discovery (it is in app/Listeners and type-hints the event).
 * Do not also register it with Event::listen in AppServiceProvider: it would
 * then run twice for every registration.
 */
class QueueNewAccountSetup
{
    public function handle(UserRegistered $event): void
    {
        CreateMainLocation::dispatch($event->userId);

        // Chained, because the owner is assigned to the workspace the first job
        // makes. The location needs nothing from either, so it is not held up
        // behind them.
        Bus::chain([
            new CreateStarterWorkspace($event->userId),
            new AddOwnerAsStaff($event->userId),
        ])->dispatch();
    }
}
