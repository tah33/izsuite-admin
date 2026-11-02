<?php

namespace App\Jobs;

use App\Repositories\User\UserRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gives a new account's business its default roles and its owner as Business
 * Owner (see SetUpNewWorkspace) - Doc 12 section 11.
 *
 * Runs after CreateStarterWorkspace (QueueNewAccountSetup chains them), so the
 * account's first workspace - and its apps, which the roles' permissions
 * follow - are there. Safe to run twice: a template the business has is not
 * added again, the owner joins once, and nothing is logged again.
 */
class AddDefaultRoles implements ShouldQueue
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

        // No workspace left - deleted before this ran: nothing to put them in.
        $workspace = $workspaces->oldestForUser($user);

        if (! $workspace) {
            return;
        }

        $setUp->addRoles($workspace, $user, 'registration');
    }
}
