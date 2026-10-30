<?php

namespace App\Services\Admin;

use App\Models\Billing\Package;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Apps\SyncAppsWithPackage;

/**
 * Moving a business to another plan from the admin panel (a client's page).
 * Until package checkout exists, this is how a business is upgraded or
 * downgraded.
 */
class WorkspaceService
{
    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected PackageRepository $packageRepository,
        protected SyncAppsWithPackage $sync,
    ) {}

    public function findOrFail(int $id): Workspace
    {
        return $this->workspaceRepository->findWithPackageOrFail($id);
    }

    /**
     * Puts $workspace on plan $packageId. Moving down turns off - or moves to
     * Lite - what the new plan does not include; moving up turns nothing on.
     * Answers with the plan it is now on.
     */
    public function changePackage(Workspace $workspace, int $packageId, ?User $by): Package
    {
        $from    = $workspace->package?->name ?? 'no plan';
        $package = $this->packageRepository->findOrFail($packageId);

        $this->sync->changePackage($workspace, $package, $by);

        ActivityLogService::record('updated', "Moved workspace \"{$workspace->name}\" from {$from} to {$package->name}", $workspace);

        return $package;
    }
}
