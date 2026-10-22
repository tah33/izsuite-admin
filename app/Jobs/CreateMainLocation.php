<?php

namespace App\Jobs;

use App\Models\User\User;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gives a new account's business its "Main Location", with no country or state
 * - the registration form asks for neither, and the owner fills them in later.
 *
 * Runs after CreateStarterWorkspace (QueueNewAccountSetup chains them), so the
 * account's first workspace is there to hold it. Safe to run twice: a business
 * that already has a location of that name keeps it, and nothing is logged
 * again.
 */
class CreateMainLocation implements ShouldQueue
{
    use Queueable;

    public const NAME = 'Main Location';

    public function __construct(
        public int $userId,
    ) {}

    public function handle(LocationRepository $locations, WorkspaceRepository $workspaces): void
    {
        $user = User::find($this->userId);

        // Deleted before the queue reached it: there is nobody to set up.
        if (! $user) {
            return;
        }

        // No workspace left - deleted before this ran: nothing to put it in.
        $workspace = $workspaces->oldestForUser($user);

        if (! $workspace) {
            return;
        }

        $location = $locations->firstOrCreateFor($workspace, ['name' => self::NAME]);

        if ($location->wasRecentlyCreated) {
            ActivityLogService::record('created', 'Added location "'.self::NAME.'"', $location, ['source' => 'registration'], $user);
        }
    }
}
