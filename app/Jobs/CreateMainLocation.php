<?php

namespace App\Jobs;

use App\Repositories\User\UserRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gives a new account's business its "Main Location" (see SetUpNewWorkspace),
 * with no country or state - the registration form asks for neither, and the
 * owner fills them in later.
 *
 * Runs after CreateStarterWorkspace (QueueNewAccountSetup chains them), so the
 * account's first workspace is there to hold it. Safe to run twice: a business
 * that already has a location of that name keeps it, and nothing is logged
 * again.
 */
class CreateMainLocation implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
    ) {}

    public function handle(UserRepository $users, WorkspaceRepository $workspaces, SetUpNewWorkspace $setUp): void
    {
        $user = $users->findById($this->userId);

        // Deleted before the queue reached it: there is nobody to set up.
        if (! $user) {
            return;
        }

        // No workspace left - deleted before this ran: nothing to put it in.
        $workspace = $workspaces->oldestForUser($user);

        if (! $workspace) {
            return;
        }

        $setUp->addMainLocation($workspace, $user, 'registration');
    }
}
