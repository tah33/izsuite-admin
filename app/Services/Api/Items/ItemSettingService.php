<?php

namespace App\Services\Api\Items;

use App\Models\User\Workspace;
use App\Services\Shared\ActivityLogService;

/**
 * Settings > Items: for now, whether the business uses Brand Management (Doc
 * 04 section 7). It lives on the workspace, and a change bumps its nav_version
 * (see Workspace): the sidebar's payload carries it to every page, so the item
 * forms and list know whether to show brands.
 */
class ItemSettingService
{
    public function settings(Workspace $workspace): array
    {
        return ['brands_enabled' => (bool) $workspace->brands_enabled];
    }

    /** Turning brands off hides them; the brands and the items' choices are kept. */
    public function save(Workspace $workspace, bool $brandsEnabled): array
    {
        $workspace->forceFill(['brands_enabled' => $brandsEnabled])->save();

        // Logged here, like every save, so LogApiWriteActivity does not write
        // its own vaguer entry.
        ActivityLogService::record('updated', ($brandsEnabled ? 'Turned on' : 'Turned off').' Brand Management', $workspace, [
            'source' => 'api',
            'fields' => $workspace->wasChanged('brands_enabled') ? ['brands_enabled'] : [],
        ]);

        return $this->settings($workspace);
    }
}
