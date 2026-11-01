<?php

namespace App\Services\User\Workspaces;

use App\Models\User\ExpenseCategory;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\UserStaff;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseCategoryRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\UserStaffRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Str;

/**
 * What every business starts with besides its apps (see ProvisionWorkspaceApps):
 * a "Main Location", with no country or state - the owner fills those in later -
 * its owner on its staff: their name and e-mail, active, assigned to it - and
 * the starter list of expense categories (Doc 10 section 7), its own to change.
 *
 * Registration does this for an account's first business, from the queue (see
 * QueueNewAccountSetup); the Workspace page for every business added after, as
 * it is made. Safe to run twice: a business that already has its "Main
 * Location", its owner on its staff (matched by e-mail), or a category of a
 * starter name keeps them, and nothing is logged again.
 *
 * $source is where the log entries say it came from: 'registration' or 'api'.
 */
class SetUpNewWorkspace
{
    public const LOCATION_NAME = 'Main Location';

    public function __construct(
        private LocationRepository $locations,
        private UserStaffRepository $staffMembers,
        private ExpenseCategoryRepository $expenseCategories,
    ) {}

    public function handle(Workspace $workspace, User $owner, string $source): void
    {
        $this->addMainLocation($workspace, $owner, $source);
        $this->addOwnerAsStaff($workspace, $owner, $source);
        $this->addExpenseCategories($workspace, $owner, $source);
    }

    public function addMainLocation(Workspace $workspace, User $owner, string $source): Location
    {
        $location = $this->locations->firstOrCreateFor($workspace, ['name' => self::LOCATION_NAME]);

        if ($location->wasRecentlyCreated) {
            ActivityLogService::record('created', 'Added location "'.self::LOCATION_NAME.'"', $location, ['source' => $source], $owner);
        }

        return $location;
    }

    /** The starter expense categories (ExpenseCategory::STARTER) - one log entry for all of them. */
    public function addExpenseCategories(Workspace $workspace, User $owner, string $source): void
    {
        $added = $this->expenseCategories->addStarter($workspace);

        if ($added > 0) {
            ActivityLogService::record('created', $added === count(ExpenseCategory::STARTER) ? 'Added the starter expense categories' : "Added {$added} starter expense categories", $workspace, ['source' => $source], $owner);
        }
    }

    /**
     * The name is cut to fit its column: a first and a last name may each be
     * as long as that column on their own.
     */
    public function addOwnerAsStaff(Workspace $workspace, User $owner, string $source): UserStaff
    {
        $staff = $this->staffMembers->firstOrCreateFor(
            $workspace,
            ['email' => $owner->email],
            ['name' => Str::limit($owner->name, 255, ''), 'status' => UserStaff::STATUS_ACTIVE],
        );

        if ($staff->wasRecentlyCreated) {
            ActivityLogService::record('created', 'Added staff member "'.Str::limit($staff->name, 100).'"', $staff, ['source' => $source], $owner);
        }

        $this->staffMembers->assignWorkspace($staff, $workspace->id);

        return $staff;
    }
}
