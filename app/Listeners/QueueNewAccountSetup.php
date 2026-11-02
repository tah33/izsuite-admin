<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Jobs\AddExpenseCategories;
use App\Jobs\AddOwnerAsStaff;
use App\Jobs\AddPaymentTerms;
use App\Jobs\CreateMainLocation;
use App\Jobs\CreateStarterWorkspace;
use Illuminate\Support\Facades\Bus;

/**
 * Queues what a new account starts with: the "Billing Starter" workspace, its
 * "Main Location", the owner as a staff member of it, and its starter expense
 * categories and payment terms.
 *
 * Only queues - registration does not wait for any of it - and only once the
 * new account is committed, so a worker never looks for a user that is not
 * saved yet. Set on the chain's first job, which queues the rest as it ends,
 * rather than on the job classes: the seeder's dispatchSync() of them must not
 * wait for a commit.
 *
 * The only thing that sets up a new account: nothing else listens for
 * UserRegistered to make a workspace, location or staff member.
 *
 * Found by event discovery (it is in app/Listeners and type-hints the event).
 * Do not also register it with Event::listen in AppServiceProvider: it would
 * then run twice for every registration.
 */
class QueueNewAccountSetup
{
    public function handle(UserRegistered $event): void
    {
        // Chained, because the location, the staff member and the categories
        // all belong to the workspace the first job makes.
        Bus::chain([
            (new CreateStarterWorkspace($event->userId))->afterCommit(),
            new CreateMainLocation($event->userId),
            new AddOwnerAsStaff($event->userId),
            new AddExpenseCategories($event->userId),
            new AddPaymentTerms($event->userId),
        ])->dispatch();
    }
}
