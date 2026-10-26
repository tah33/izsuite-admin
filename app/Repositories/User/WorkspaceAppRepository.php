<?php

namespace App\Repositories\User;

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
}
