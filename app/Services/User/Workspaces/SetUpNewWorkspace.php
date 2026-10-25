<?php

namespace App\Services\User\Workspaces;

use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\UserStaff;
use App\Models\User\Workspace;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\UserStaffRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Str;

/**
 * What every business starts with besides its apps (see ProvisionWorkspaceApps):
 * a "Main Location", with no country or state - the owner fills those in later -
 * and its owner on its staff: their name and e-mail, active, assigned to it.
 *
 * Registration does this for an account's first business, from the queue (see
 * QueueNewAccountSetup); the Workspace page for every business added after, as
 * it is made. Safe to run twice: a business that already has its "Main
 * Location", or its owner on its staff (matched by e-mail), keeps them, and
 * nothing is logged again.
 *
 * $source is where the log entries say it came from: 'registration' or 'api'.
 */
class SetUpNewWorkspace
{
    public const LOCATION_NAME = 'Main Location';

    public function __construct(
        private LocationRepository $locations,
        private UserStaffRepository $staffMembers,
    ) {}

    public function handle(Workspace $workspace, User $owner, string $source): void
    {
        $this->addMainLocation($workspace, $owner, $source);
        $this->addOwnerAsStaff($workspace, $owner, $source);
    }

    public function addMainLocation(Workspace $workspace, User $owner, string $source): Location
    {
        $location = $this->locations->firstOrCreateFor($workspace, ['name' => self::LOCATION_NAME]);

        if ($location->wasRecentlyCreated) {
            ActivityLogService::record('created', 'Added location "'.self::LOCATION_NAME.'"', $location, ['source' => $source], $owner);
        }

        return $location;
    }

    public function addOwnerAsStaff(Workspace $workspace, User $owner, string $source): UserStaff
    {
        $staff = $this->staffMembers->firstOrCreateFor(
            $workspace,
            ['email' => $owner->email],
            ['name' => $owner->name, 'status' => UserStaff::STATUS_ACTIVE],
        );

        if ($staff->wasRecentlyCreated) {
            ActivityLogService::record('created', 'Added staff member "'.Str::limit($staff->name, 100).'"', $staff, ['source' => $source], $owner);
        }

        $this->staffMembers->assignWorkspace($staff, $workspace->id);

        return $staff;
    }
}
