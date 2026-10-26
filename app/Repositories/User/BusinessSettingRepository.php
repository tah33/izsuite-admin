<?php

namespace App\Repositories\User;

use App\Models\User\BusinessSetting;
use App\Models\User\Workspace;

class BusinessSettingRepository
{
    public function findFor(Workspace $workspace): ?BusinessSetting
    {
        return $workspace->businessSetting()->first();
    }

    /**
     * One row per business (workspace_id is unique), so saving is "write over
     * it, or create it the first time" - never a second row.
     */
    public function save(Workspace $workspace, array $data): BusinessSetting
    {
        return BusinessSetting::updateOrCreate(
            ['workspace_id' => $workspace->id],
            [...$data, 'user_id' => $workspace->user_id],
        );
    }
}
