<?php

namespace App\Services\User\Apps;

use App\Enums\Edition;
use App\Events\AppDeactivated;
use App\Models\Billing\Package;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceAppLog;
use App\Repositories\User\WorkspaceAppRepository;
use App\Repositories\User\WorkspaceRepository;
use Illuminate\Support\Facades\DB;

/**
 * Brings a business's apps within its plan after the plan changes (Doc 01
 * section 13).
 *
 * It only ever moves an app down - Full to Lite where the plan still has Lite,
 * otherwise off with its records kept readable - so it can never turn anything
 * on by itself. An upgrade changes nothing here: the new apps simply become
 * available in the Apps Center.
 */
class SyncAppsWithPackage
{
    public function __construct(
        private WorkspaceAppRepository $workspaceApps,
        private WorkspaceRepository $workspaces,
    ) {}

    /** Puts the business on $package, then brings its apps within it. */
    public function changePackage(Workspace $workspace, Package $package, ?User $by = null): void
    {
        $this->workspaces->setPackage($workspace, $package);

        $this->handle($workspace, $by);
    }

    public function handle(Workspace $workspace, ?User $by = null): void
    {
        $package = $this->workspaces->packageOf($workspace);
        $moved   = [];

        DB::transaction(function () use ($workspace, $package, $by, &$moved) {
            foreach (WorkspaceApps::for($workspace)->active() as $row) {
                $from = $row->app;

                if ($package?->allows($from)) {
                    continue;
                }

                $lite = $from->edition === Edition::Full ? $from->liteEdition() : null;

                if ($lite && $lite->isAvailable() && $package?->allows($lite)) {
                    $this->workspaceApps->update($row, ['app_id' => $lite->id]);                                                    // Sales → Sales Lite
                } else {
                    $lite = null;
                    $this->workspaceApps->update($row, ['status' => WorkspaceApp::STATUS_INACTIVE, 'deactivated_at' => now()]);      // read-only
                }

                $this->workspaceApps->log($workspace, [
                    'app_id'      => $lite?->id ?? $from->id,
                    'from_app_id' => $from->id,
                    'action'      => $lite ? WorkspaceAppLog::FALLBACK : WorkspaceAppLog::DEACTIVATED,
                    'user_id'     => $by?->id,
                ]);

                $moved[] = $from;
            }

            $this->workspaces->bumpNavVersion($workspace);
        });

        foreach ($moved as $app) {
            AppDeactivated::dispatch($workspace, $app);
        }
    }
}
