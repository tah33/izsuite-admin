<?php

namespace App\Jobs;

use App\Models\User\User;
use App\Models\User\UserStaff;
use App\Repositories\User\UserStaffRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Adds a new account's owner to its business's staff - their name and e-mail,
 * active - assigned to that business.
 *
 * Runs after CreateStarterWorkspace (QueueNewAccountSetup chains them), so the
 * account's first workspace is there to hold the staff member. Safe to run
 * twice: the owner is matched by e-mail, and a workspace already assigned is
 * not added again.
 */
class AddOwnerAsStaff implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
    ) {}

    public function handle(UserStaffRepository $staffMembers, WorkspaceRepository $workspaces): void
    {
        $user = User::find($this->userId);

        // Deleted before the queue reached it: there is nobody to set up.
        if (! $user) {
            return;
        }

        // No workspace left - deleted before this ran: nothing to put them in.
        $workspace = $workspaces->oldestForUser($user);

        if (! $workspace) {
            return;
        }

        $staff = $staffMembers->firstOrCreateFor(
            $workspace,
            ['email' => $user->email],
            ['name' => $user->name, 'status' => UserStaff::STATUS_ACTIVE],
        );

        if ($staff->wasRecentlyCreated) {
            ActivityLogService::record('created', 'Added staff member "'.Str::limit($staff->name, 100).'"', $staff, ['source' => 'registration'], $user);
        }

        $staffMembers->assignWorkspace($staff, $workspace->id);
    }
}
