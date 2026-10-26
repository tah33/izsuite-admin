<?php

namespace App\Services\User\Apps;

use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceAppLog;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\User\WorkspaceAppRepository;
use App\Repositories\User\WorkspaceRepository;

/**
 * Turns on what a new business starts with: every Lite app its plan includes -
 * on Billing Starter, Sales Lite, Purchases Lite and Expenses Lite.
 *
 * Only for families the business has no row for yet, so it is safe to run
 * again, and it never turns back on something the business turned off. The
 * catalog seeder runs it over existing businesses for that reason.
 */
class ProvisionWorkspaceApps
{
    public function __construct(
        private WorkspaceAppRepository $workspaceApps,
        private WorkspaceRepository $workspaces,
        private PackageRepository $packages,
    ) {}

    public function handle(Workspace $workspace, ?User $by = null): void
    {
        $package = $workspace->package;

        if (! $package) {
            return;
        }

        $taken = $this->workspaceApps->families($workspace);

        $lite = $this->packages->availableLiteApps($package)
            ->reject(fn (Application $app) => $taken->contains($app->family_key));

        if ($lite->isEmpty()) {
            return;
        }

        foreach ($lite as $app) {
            $this->workspaceApps->create($workspace, [
                'family_key'   => $app->family_key,
                'app_id'       => $app->id,
                'status'       => WorkspaceApp::STATUS_ACTIVE,
                'activated_by' => $by?->id,
                'activated_at' => now(),
            ]);

            $this->workspaceApps->log($workspace, [
                'app_id'  => $app->id,
                'action'  => WorkspaceAppLog::ACTIVATED,
                'user_id' => $by?->id,
            ]);
        }

        $this->workspaces->bumpNavVersion($workspace);
    }
}
