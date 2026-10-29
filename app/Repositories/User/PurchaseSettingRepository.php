<?php

namespace App\Repositories\User;

use App\Models\User\PurchaseSetting;
use App\Models\User\Workspace;

/** The purchase_settings table: one row per business, once it first saves them. */
class PurchaseSettingRepository
{
    /** The business's settings - unsaved, with the defaults, if it has never saved them. */
    public function forWorkspace(Workspace $workspace): PurchaseSetting
    {
        return $workspace->purchaseSetting()->firstOrNew();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function save(Workspace $workspace, array $data): PurchaseSetting
    {
        return $workspace->purchaseSetting()->updateOrCreate([], $data);
    }
}
