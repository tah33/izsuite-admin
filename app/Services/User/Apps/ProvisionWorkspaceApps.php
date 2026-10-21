<?php

namespace App\Services\User\Apps;

use App\Enums\Edition;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceAppLog;

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
    public function handle(Workspace $workspace, ?User $by = null): void
    {
        $package = $workspace->package;

        if (! $package) {
            return;
        }

        $taken = $workspace->workspaceApps()->pluck('family_key');

        $lite = $package->apps()
            ->where('edition', Edition::Lite)
            ->where('is_active', true)
            ->where('status', 'available')
            ->get()
            ->reject(fn (Application $app) => $taken->contains($app->family_key));

        if ($lite->isEmpty()) {
            return;
        }

        foreach ($lite as $app) {
            $workspace->workspaceApps()->create([
                'family_key'   => $app->family_key,
                'app_id'       => $app->id,
                'status'       => WorkspaceApp::STATUS_ACTIVE,
                'activated_by' => $by?->id,
                'activated_at' => now(),
            ]);

            $workspace->appLogs()->create([
                'app_id'  => $app->id,
                'action'  => WorkspaceAppLog::ACTIVATED,
                'user_id' => $by?->id,
            ]);
        }

        $workspace->increment('nav_version');
    }
}
