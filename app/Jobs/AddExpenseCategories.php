<?php

namespace App\Jobs;

use App\Repositories\User\UserRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gives a new account's business its starter expense categories (see
 * SetUpNewWorkspace) - Rent, Utilities and the rest of Doc 10 section 2.
 *
 * Runs after CreateStarterWorkspace (QueueNewAccountSetup chains them), so the
 * account's first workspace is there to hold them. Safe to run twice: a
 * category of a starter name is not added again, and nothing is logged again.
 */
class AddExpenseCategories implements ShouldQueue
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

        $setUp->addExpenseCategories($workspace, $user, 'registration');
    }
}
