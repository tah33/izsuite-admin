<?php

namespace App\Repositories\User;

use App\Models\User\CrmSetting;
use App\Models\User\Workspace;

/** Settings > CRM (Doc 17 Phase A1). */
class CrmSettingRepository
{
    /** The business's settings - unsaved, with the defaults, if it has never saved them. */
    public function forWorkspace(Workspace $workspace): CrmSetting
    {
        return $workspace->crmSetting()->firstOrNew();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function save(Workspace $workspace, array $data): CrmSetting
    {
        return $workspace->crmSetting()->updateOrCreate([], $data);
    }
}
