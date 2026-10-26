<?php

namespace App\Services\User\Apps;

use App\Enums\Edition;
use App\Events\AppDeactivated;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceAppLog;
use App\Repositories\User\WorkspaceAppRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an app off for a business (Doc 01 sections 8 and 9).
 *
 * A Full app falls back to its Lite edition where the plan allows that;
 * otherwise the family's row becomes inactive and its records stay readable.
 * Refused, not cascaded, while another active app still needs the family.
 */
class DeactivateApp
{
    public function __construct(
        private AppDependencies $dependencies,
        private WorkspaceAppRepository $workspaceApps,
        private WorkspaceRepository $workspaces,
    ) {}

    /**
     * @return Application|null the Lite edition it fell back to, if any
     */
    public function handle(Workspace $workspace, Application $app, User $by): ?Application
    {
        abort_unless($workspace->isOwnedBy($by), 403, 'Only the owner of this business can turn apps on or off.');

        $apps = WorkspaceApps::for($workspace);
        $row  = $apps->row($app->family_key);

        if (! $row?->isActive() || $row->app_id !== $app->id) {
            $this->refuse("{$app->name} is not turned on.");
        }

        $fallback = $this->fallbackFor($workspace, $app);
        $broken   = $this->dependencies->brokenBy($apps, $app->family_key, $fallback?->edition);

        if ($broken->isNotEmpty()) {
            $this->refuse('Turn these off first: '.$broken->implode(', ').'.');
        }

        DB::transaction(function () use ($workspace, $app, $fallback, $by) {
            $row = $this->workspaceApps->lockRowOrFail($workspace, $app->family_key);

            $fallback
                ? $this->workspaceApps->update($row, ['app_id' => $fallback->id])                                                    // Sales → Sales Lite
                : $this->workspaceApps->update($row, ['status' => WorkspaceApp::STATUS_INACTIVE, 'deactivated_at' => now()]);      // records kept

            $this->workspaceApps->log($workspace, [
                'app_id'      => $fallback?->id ?? $app->id,
                'from_app_id' => $app->id,
                'action'      => $fallback ? WorkspaceAppLog::FALLBACK : WorkspaceAppLog::DEACTIVATED,
                'user_id'     => $by->id,
            ]);

            $this->workspaces->bumpNavVersion($workspace);
        });

        ActivityLogService::record(
            'updated',
            $fallback ? "Moved {$app->name} back to {$fallback->name}" : "Turned off {$app->name}",
            $workspace,
            ['app' => $app->app_key],
            $by,
        );

        AppDeactivated::dispatch($workspace, $app);

        return $fallback;
    }

    /** Where turning $app off lands: its Lite edition, if it is Full and the plan still has Lite. */
    public function fallbackFor(Workspace $workspace, Application $app): ?Application
    {
        if ($app->edition !== Edition::Full) {
            return null;
        }

        $lite = $app->liteEdition();

        return $lite && $lite->isAvailable() && $workspace->package?->allows($lite) ? $lite : null;
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['app' => $message]);
    }
}
