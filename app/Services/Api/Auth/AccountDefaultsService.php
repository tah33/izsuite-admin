<?php

namespace App\Services\Api\Auth;

use App\Models\User\User;
use App\Models\User\UserStaff;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\UserRepository;
use App\Repositories\User\UserStaffRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Str;

/**
 * What a new account is given to start with: the "Billing Starter" workspace,
 * the "Main Location", and the owner as the first of their own staff, assigned
 * to that workspace.
 *
 * Run by the UserRegistered listeners, each on its own and in whatever order the
 * queue gets to them, so every method here:
 *
 *  - may run twice (a retried job) and still leaves one of each: it looks for
 *    what is already there before making anything;
 *  - leaves the owner assigned to the workspace whichever of the two runs first.
 *    Each one, once its own row is saved, links the pair if the other is there.
 *    Saved first and looked for second, at least one of them always sees both -
 *    so the link is made even when the two jobs run side by side;
 *  - does nothing for an account that has been deleted before the job ran.
 */
class AccountDefaultsService
{
    public const WORKSPACE_NAME = 'Billing Starter';

    public const LOCATION_NAME = 'Main Location';

    public function __construct(
        protected UserRepository $userRepository,
        protected WorkspaceRepository $workspaceRepository,
        protected LocationRepository $locationRepository,
        protected UserStaffRepository $userStaffRepository,
    ) {}

    public function createWorkspace(int $userId): void
    {
        $user = $this->userRepository->find($userId);

        if (! $user) {
            return;
        }

        $workspace = $this->workspaceRepository->firstOrCreateForUser($user, ['name' => self::WORKSPACE_NAME]);

        if ($workspace->wasRecentlyCreated) {
            $this->log($user, 'Added workspace "'.self::WORKSPACE_NAME.'"');
        }

        $this->assignOwnerToWorkspace($user);
    }

    /**
     * Named, and nothing more: registration asks for no country or state, so
     * the owner adds them from Settings > Locations if they want them.
     */
    public function createLocation(int $userId): void
    {
        $user = $this->userRepository->find($userId);

        if (! $user) {
            return;
        }

        $location = $this->locationRepository->firstOrCreateForUser($user, ['name' => self::LOCATION_NAME]);

        if ($location->wasRecentlyCreated) {
            $this->log($user, 'Added location "'.self::LOCATION_NAME.'"');
        }
    }

    /**
     * The owner as a staff member: their name and e-mail, active. Registration
     * asks for no phone number, so the row has none - the Staff form asks for
     * one the first time it is edited.
     *
     * Found again by the e-mail, which is the owner's own and unique to them.
     * The name is cut to fit its column: a first and a last name may each be
     * as long as that column on their own.
     */
    public function createOwnerStaffMember(int $userId): void
    {
        $user = $this->userRepository->find($userId);

        if (! $user) {
            return;
        }

        $staff = $this->userStaffRepository->firstOrCreateForUser($user, ['email' => $user->email], [
            'name'   => Str::limit($user->name, 255, ''),
            'status' => UserStaff::STATUS_ACTIVE,
        ]);

        if ($staff->wasRecentlyCreated) {
            $this->log($user, 'Added staff member "'.Str::limit($staff->name, 100).'"');
        }

        $this->assignOwnerToWorkspace($user);
    }

    private function assignOwnerToWorkspace(User $user): void
    {
        $workspace = $this->workspaceRepository->findByNameForUser($user, self::WORKSPACE_NAME);
        $staff     = $this->userStaffRepository->findByEmailForUser($user, $user->email);

        if ($workspace && $staff) {
            $this->userStaffRepository->assignWorkspace($staff, $workspace->id);
        }
    }

    /**
     * Into the new owner's own activity log. A queued job has nobody signed in,
     * so the log is handed the user as its subject - which is what gives the
     * entry its user_id, the way AuthService::register logs the account itself.
     */
    private function log(User $user, string $description): void
    {
        ActivityLogService::record('created', $description, $user, ['source' => 'registration']);
    }
}
