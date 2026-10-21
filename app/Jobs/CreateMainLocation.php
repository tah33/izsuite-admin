<?php

namespace App\Jobs;

use App\Models\User\User;
use App\Repositories\User\LocationRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gives a new account its "Main Location", with no country or state - the
 * registration form asks for neither, and the owner fills them in later.
 *
 * Safe to run twice: an account that already has a location of that name keeps
 * it, and nothing is logged again.
 */
class CreateMainLocation implements ShouldQueue
{
    use Queueable;

    public const NAME = 'Main Location';

    public function __construct(
        public int $userId,
    ) {}

    public function handle(LocationRepository $locations): void
    {
        $user = User::find($this->userId);

        // Deleted before the queue reached it: there is nobody to set up.
        if (! $user) {
            return;
        }

        $location = $locations->firstOrCreateForUser($user, ['name' => self::NAME]);

        if ($location->wasRecentlyCreated) {
            ActivityLogService::record('created', 'Added location "'.self::NAME.'"', $location, ['source' => 'registration'], $user);
        }
    }
}
