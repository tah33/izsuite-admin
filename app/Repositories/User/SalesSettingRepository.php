<?php

namespace App\Repositories\User;

use App\Models\User\SalesSetting;
use App\Models\User\Workspace;

/** The sales_settings table: one row per business, once it first saves them. */
class SalesSettingRepository
{
    /** The business's settings - unsaved, with the defaults, if it has never saved them. */
    public function forWorkspace(Workspace $workspace): SalesSetting
    {
        return $workspace->salesSetting()->firstOrNew();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function save(Workspace $workspace, array $data): SalesSetting
    {
        return $workspace->salesSetting()->updateOrCreate([], $data);
    }
}
