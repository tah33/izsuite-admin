<?php

namespace App\Repositories\User;

use App\Models\Frontend\Application;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceAppLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * A business's apps - one row per app family it has had on: the edition and
 * whether it is on now - and the log of every change to them.
 */
class WorkspaceAppRepository
{
    /** The business's rows with each app and what it needs, keyed by family. */
    public function rowsByFamily(Workspace $workspace): Collection
    {
        return $workspace->workspaceApps()->with('app.dependencies')->get()->keyBy('family_key');
    }

    /** The families the business has a row for. */
    public function families(Workspace $workspace): SupportCollection
    {
        return $workspace->workspaceApps()->pluck('family_key');
    }

    /** @return list<string> the families the business has on now */
    public function activeFamilies(Workspace $workspace): array
    {
        return $workspace->workspaceApps()->where('status', WorkspaceApp::STATUS_ACTIVE)->pluck('family_key')->all();
    }

    /** $family's row, locked until the transaction ends. */
    public function lockRow(Workspace $workspace, string $family): ?WorkspaceApp
    {
        return $workspace->workspaceApps()->where('family_key', $family)->lockForUpdate()->first();
    }

    public function lockRowOrFail(Workspace $workspace, string $family): WorkspaceApp
    {
        return $workspace->workspaceApps()->where('family_key', $family)->lockForUpdate()->firstOrFail();
    }

    public function create(Workspace $workspace, array $data): WorkspaceApp
    {
        return $workspace->workspaceApps()->create($data);
    }

    /** $family's row, made or changed. */
    public function saveForFamily(Workspace $workspace, string $family, array $data): WorkspaceApp
    {
        return $workspace->workspaceApps()->updateOrCreate(['family_key' => $family], $data);
    }

    public function update(WorkspaceApp $row, array $data): void
    {
        $row->update($data);
    }

    public function log(Workspace $workspace, array $data): WorkspaceAppLog
    {
        return $workspace->appLogs()->create($data);
    }

    /**
     * $app off for every business with it on (Doc 15 Phase E2: retired) -
     * each logged, its sidebar rebuilt, its records kept. How many.
     */
    public function deactivateEverywhere(Application $app): int
    {
        $rows = WorkspaceApp::where('app_id', $app->id)->where('status', WorkspaceApp::STATUS_ACTIVE)->get(['id', 'workspace_id']);

        if ($rows->isEmpty()) {
            return 0;
        }

        WorkspaceApp::whereIn('id', $rows->pluck('id'))->update(['status' => WorkspaceApp::STATUS_INACTIVE, 'deactivated_at' => now()]);
        WorkspaceAppLog::insert($rows->map(fn (WorkspaceApp $row) => [
            'workspace_id' => $row->workspace_id, 'app_id' => $app->id, 'from_app_id' => null, 'action' => WorkspaceAppLog::DEACTIVATED, 'user_id' => null, 'created_at' => now(),
        ])->all());
        Workspace::whereIn('id', $rows->pluck('workspace_id'))->increment('nav_version');

        return $rows->count();
    }
}
