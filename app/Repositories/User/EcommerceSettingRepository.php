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
}
