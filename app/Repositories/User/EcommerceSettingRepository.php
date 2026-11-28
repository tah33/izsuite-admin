<?php

namespace App\Repositories\User;

use App\Models\User\EcommerceSetting;
use App\Models\User\Workspace;

/** Settings → eCommerce (Doc 19 Phase A1). */
class EcommerceSettingRepository
{
    /** The business's settings - unsaved, with the defaults, if it has never saved them. */
    public function forWorkspace(Workspace $workspace): EcommerceSetting
    {
        return $workspace->ecommerceSetting()->firstOrNew();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function save(Workspace $workspace, array $data): EcommerceSetting
    {
        return $workspace->ecommerceSetting()->updateOrCreate([], $data);
    }

    /**
     * Phase H3a: how many hours a cart whose customer is known goes unused
     * before it is Abandoned, for each of $workspaceIds - the default where a
     * business never saved its settings.
     *
     * @param  list<int>  $workspaceIds
     * @return array<int, int>
     */
    public function abandonedHoursOf(array $workspaceIds): array
    {
        $saved   = EcommerceSetting::query()->whereIn('workspace_id', $workspaceIds)->pluck('abandoned_hours', 'workspace_id');
        $default = (int) (new EcommerceSetting)->abandoned_hours;

        return collect($workspaceIds)->mapWithKeys(fn (int $id) => [$id => (int) ($saved[$id] ?? $default)])->all();
    }
}
