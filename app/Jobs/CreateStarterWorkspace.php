<?php

namespace App\Jobs;

use App\Models\User\User;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gives a new account its "Billing Starter" workspace, with no app.
 *
 * Safe to run twice - a retried job must still leave one: an account that
 * already has a workspace of that name keeps it, and nothing is logged again.
 */
class CreateStarterWorkspace implements ShouldQueue
{
    use Queueable;

    /** What it is called, and how AddOwnerAsStaff finds it afterwards. */
    public const NAME = 'Billing Starter';

    public function __construct(
        public int $userId,
    ) {}

    public function handle(WorkspaceRepository $workspaces): void
    {
        $user = User::find($this->userId);

        // Deleted before the queue reached it: there is nobody to set up.
        if (! $user) {
            return;
        }

        $workspace = $workspaces->firstOrCreateForUser($user, ['name' => self::NAME]);

        if ($workspace->wasRecentlyCreated) {
            ActivityLogService::record('created', 'Added workspace "'.self::NAME.'"', $workspace, ['source' => 'registration'], $user);
        }
    }
}
