<?php

namespace App\Services\User\Apps;

use App\Events\AppActivated;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceAppLog;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an app on for a business, or replaces its family's Lite edition with
 * Full (Doc 01 sections 8 and 9).
 *
 * Never a second row for a family: if it has one, the edition changes in place
 * - the UNIQUE (workspace_id, family_key) key backs that up if two requests
 * race - and Full goes on using the records Lite made.
 */
class ActivateApp
{
    public function __construct(
        private AppDependencies $dependencies,
    ) {}

    /**
     * @return Application|null the edition it replaced (Sales Lite, for Sales), if any
     */
    public function handle(Workspace $workspace, Application $app, User $by): ?Application
    {
        abort_unless($workspace->isOwnedBy($by), 403, 'Only the owner of this business can turn apps on or off.');

        if (! $app->isAvailable()) {
            $this->refuse("{$app->name} is not available yet.");
        }

        abort_unless((bool) $workspace->package?->allows($app), 403, 'Your plan does not include this app.');

        $apps = WorkspaceApps::for($workspace);
        $row  = $apps->row($app->family_key);

        if ($row?->isActive() && $row->app_id === $app->id) {
            return null;
        }

        if ($row?->isActive() && $row->app->edition->rank() > $app->edition->rank()) {
            $this->refuse("{$row->app->name} is on, and already includes {$app->name}.");
        }

        $missing = $this->dependencies->missing($apps, $app);

        if ($missing->isNotEmpty()) {
            $this->refuse('Turn on first: '.$missing->implode(', ').'.');
        }

        $replaced = DB::transaction(function () use ($workspace, $app, $by): ?Application {
            $row = $workspace->workspaceApps()->where('family_key', $app->family_key)->lockForUpdate()->first();

            // The edition this one takes over from - Sales Lite, for Sales.
            $replaced = $row?->isActive() ? $row->app : null;

            $workspace->workspaceApps()->updateOrCreate(['family_key' => $app->family_key], [
                'app_id'         => $app->id,
                'status'         => WorkspaceApp::STATUS_ACTIVE,
                'activated_by'   => $by->id,
                'activated_at'   => now(),
                'deactivated_at' => null,
            ]);

            $workspace->appLogs()->create([
                'app_id'      => $app->id,
                'from_app_id' => $replaced?->id,
                'action'      => $replaced ? WorkspaceAppLog::REPLACED : WorkspaceAppLog::ACTIVATED,
                'user_id'     => $by->id,
            ]);

            $workspace->increment('nav_version');

            return $replaced;
        });

        ActivityLogService::record(
            'updated',
            $replaced ? "Upgraded {$replaced->name} to {$app->name}" : "Turned on {$app->name}",
            $workspace,
            ['app' => $app->app_key],
            $by,
        );

        AppActivated::dispatch($workspace, $app);

        return $replaced;
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['app' => $message]);
    }
}
