<?php

namespace App\Services\User\Workspaces;

use App\Models\Billing\Package;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\UserStaffRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Files\StorageUsage;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * A package's count limits (Doc 01 section 4, Doc 13), as the admin sets them:
 * how many businesses on it one account may own, and how many users and
 * locations each business has. Empty is no limit.
 *
 * Adding past a limit is refused with a 403 that says so. A business already
 * over one - after a downgrade, or a limit lowered - keeps everything; it only
 * cannot add more. A business's users are its owner and the staff added to
 * it; the owner is on its staff too, and counted once.
 */
class PlanLimits
{
    public function __construct(
        protected PackageRepository $packageRepository,
        protected WorkspaceRepository $workspaceRepository,
        protected LocationRepository $locationRepository,
        protected UserStaffRepository $userStaffRepository,
        protected StorageUsage $storageUsage,
    ) {}

    /** A new business starts on Billing Starter, so the account may own only so many there. */
    public function ensureCanAddBusiness(User $owner): void
    {
        $starter = $this->packageRepository->starter();

        if ($starter?->business_limit !== null && $this->workspaceRepository->countForUserOnPackage($owner, $starter->id) >= $starter->business_limit) {
            $this->refuse($starter->business_limit === 1
                ? "Your account already has its {$starter->name} business. Another business needs a paid plan."
                : "Your account already has {$starter->business_limit} businesses on {$starter->name}. Another business needs a paid plan.");
        }
    }

    public function ensureCanAddUser(Workspace $workspace): void
    {
        $package = $this->workspaceRepository->packageOf($workspace);

        if ($package?->user_limit !== null && $this->users($workspace) >= $package->user_limit) {
            $this->refuse($this->reached($package, $package->user_limit, 'user', 'users').' Upgrade the plan to add staff.');
        }
    }

    public function ensureCanAddLocation(Workspace $workspace): void
    {
        $package = $this->workspaceRepository->packageOf($workspace);

        if ($package?->location_limit !== null && $this->locationRepository->countFor($workspace) >= $package->location_limit) {
            $this->refuse($this->reached($package, $package->location_limit, 'location', 'locations').' Upgrade the plan to add another.');
        }
    }

    /**
     * Settings > Subscription's Usage and Limits (Doc 13 sections 13.11 and
     * 13.14): the business's storage as StorageUsage measures it, and how
     * many users and locations it has of how many its plan gives it (null for
     * no limit).
     *
     * @return array{storage: array, users: array{used: int, limit: ?int}, locations: array{used: int, limit: ?int}}
     */
    public function usage(Workspace $workspace): array
    {
        $package = $this->workspaceRepository->packageOf($workspace);

        return [
            'storage'   => $this->storageUsage->summary($workspace),
            'users'     => ['used' => $this->users($workspace), 'limit' => $package?->user_limit],
            'locations' => ['used' => $this->locationRepository->countFor($workspace), 'limit' => $package?->location_limit],
        ];
    }

    /** Its owner, and every staff member besides them. */
    private function users(Workspace $workspace): int
    {
        return 1 + $this->userStaffRepository->countBesides($workspace, $this->workspaceRepository->ownerEmail($workspace));
    }

    /** "Billing Starter includes one user." */
    private function reached(Package $package, int $limit, string $one, string $many): string
    {
        return "{$package->name} includes ".($limit === 1 ? "one {$one}" : "{$limit} {$many}").'.';
    }

    private function refuse(string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], 403));
    }
}
