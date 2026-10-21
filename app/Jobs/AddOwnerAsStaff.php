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
 * Adds a new account's owner to its own staff - their name and e-mail, active -
 * assigned to the "Billing Starter" workspace.
 *
 * Runs after CreateStarterWorkspace (QueueNewAccountSetup chains them), so the
 * workspace is there to assign. Safe to run twice: the owner is matched by
 * e-mail, and a workspace already assigned is not added again.
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

        $staff = $staffMembers->firstOrCreateForUser(
            $user,
            ['email' => $user->email],
            ['name' => $user->name, 'status' => UserStaff::STATUS_ACTIVE],
        );

        if ($staff->wasRecentlyCreated) {
            ActivityLogService::record('created', 'Added staff member "'.Str::limit($staff->name, 100).'"', $staff, ['source' => 'registration'], $user);
        }

        // Renamed or deleted before this ran: there is nothing to assign.
        $workspace = $workspaces->findByNameForUser($user, CreateStarterWorkspace::NAME);

        if ($workspace) {
            $staffMembers->assignWorkspace($staff, $workspace->id);
        }
    }
}
