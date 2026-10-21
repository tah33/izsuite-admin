<?php

namespace App\Services\User\Apps;

use App\Enums\Edition;
use App\Events\AppDeactivated;
use App\Models\Billing\Package;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceAppLog;
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
    /** Puts the business on $package, then brings its apps within it. */
    public function changePackage(Workspace $workspace, Package $package, ?User $by = null): void
    {
        $workspace->package()->associate($package)->save();

        $this->handle($workspace, $by);
    }

    public function handle(Workspace $workspace, ?User $by = null): void
    {
        $package = $workspace->package()->first();
        $moved   = [];

        DB::transaction(function () use ($workspace, $package, $by, &$moved) {
            foreach (WorkspaceApps::for($workspace)->active() as $row) {
                $from = $row->app;

                if ($package?->allows($from)) {
                    continue;
                }

                $lite = $from->edition === Edition::Full ? $from->liteEdition() : null;

                if ($lite && $lite->isAvailable() && $package?->allows($lite)) {
                    $row->update(['app_id' => $lite->id]);                                                    // Sales → Sales Lite
                } else {
                    $lite = null;
                    $row->update(['status' => WorkspaceApp::STATUS_INACTIVE, 'deactivated_at' => now()]);      // read-only
                }

                $workspace->appLogs()->create([
                    'app_id'      => $lite?->id ?? $from->id,
                    'from_app_id' => $from->id,
                    'action'      => $lite ? WorkspaceAppLog::FALLBACK : WorkspaceAppLog::DEACTIVATED,
                    'user_id'     => $by?->id,
                ]);

                $moved[] = $from;
            }

            $workspace->increment('nav_version');
        });

        foreach ($moved as $app) {
            AppDeactivated::dispatch($workspace, $app);
        }
    }
}
