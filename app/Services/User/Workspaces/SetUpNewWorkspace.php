<?php

namespace App\Services\User\Workspaces;

use App\Models\User\ExpenseCategory;
use App\Models\User\Location;
use App\Models\User\PaymentTerm;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseCategoryRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\PaymentTermRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Repositories\User\WorkspaceRoleRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Permissions;

/**
 * What every business starts with besides its apps (see ProvisionWorkspaceApps):
 * a "Main Location" - code MAIN, its Primary, on its owner's time zone, with
 * no country or address: the owner fills those in later - the starter lists of
 * expense categories (Doc 10 section 7) and payment terms (Doc 11 section 14),
 * its own to change, and the default roles with its owner as its first user,
 * the Business Owner (Doc 12 sections 9 and 11).
 *
 * Registration does this for an account's first business, from the queue (see
 * QueueNewAccountSetup); the Workspace page for every business added after, as
 * it is made. Safe to run twice: a business that already has its "Main
 * Location", its owner as a user, a default role, or a category or term of a
 * starter's name keeps them, and nothing is logged again.
 *
 * $source is where the log entries say it came from: 'registration' or 'api'.
 */
class SetUpNewWorkspace
{
    public const LOCATION_NAME = 'Main Location';

    public const LOCATION_CODE = 'MAIN';

    public function __construct(
        private LocationRepository $locations,
        private ExpenseCategoryRepository $expenseCategories,
        private PaymentTermRepository $paymentTerms,
        private WorkspaceRoleRepository $roles,
        private WorkspaceMemberRepository $members,
    ) {}

    public function handle(Workspace $workspace, User $owner, string $source): void
    {
        $this->addMainLocation($workspace, $owner, $source);
        $this->addExpenseCategories($workspace, $owner, $source);
        $this->addPaymentTerms($workspace, $owner, $source);
        $this->addRoles($workspace, $owner, $source);
    }

    /**
     * The default role templates (Doc 12 section 11) - their permissions among
     * the apps on now; apps turned on later wait for someone to grant them -
     * and the owner as a member under Business Owner. One log entry for all.
     */
    public function addRoles(Workspace $workspace, User $owner, string $source): void
    {
        $families = WorkspaceApps::for($workspace)->permissionFamilies();

        $added = $this->roles->addTemplates($workspace, fn (string $template) => [
            'name'        => Permissions::TEMPLATES[$template][0],
            'description' => Permissions::TEMPLATES[$template][1],
            'full_access' => in_array($template, Permissions::FULL_ACCESS_TEMPLATES, true),
            'permissions' => Permissions::forTemplate($template, $families),
        ]);

        $this->members->firstOrCreateFor($workspace, $owner, $this->roles->ownerRoleOf($workspace));

        if ($added > 0) {
            ActivityLogService::record('created', 'Added the default roles', $workspace, ['source' => $source], $owner);
        }
    }

    public function addMainLocation(Workspace $workspace, User $owner, string $source): Location
    {
        $location = $this->locations->firstOrCreateFor($workspace, ['name' => self::LOCATION_NAME], [
            'code'       => self::LOCATION_CODE,
            'is_primary' => ! $this->locations->hasPrimary($workspace),
            'timezone'   => $owner->timezone ?: 'UTC',
        ]);

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

    /** The starter payment terms (PaymentTerm::STARTER) - one log entry for all of them. */
    public function addPaymentTerms(Workspace $workspace, User $owner, string $source): void
    {
        $added = $this->paymentTerms->addStarter($workspace);

        if ($added > 0) {
            ActivityLogService::record('created', $added === count(PaymentTerm::STARTER) ? 'Added the starter payment terms' : "Added {$added} starter payment terms", $workspace, ['source' => $source], $owner);
        }
    }
}
