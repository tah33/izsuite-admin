<?php

namespace App\Repositories\User;

use App\Models\User\EmailSetting;
use App\Models\User\Workspace;

/** Settings > Email, one row per business once first saved. */
class EmailSettingRepository
{
    /** The business's settings - unsaved defaults until it has any. */
    public function forWorkspace(Workspace $workspace): EmailSetting
    {
        return $workspace->emailSetting()->first() ?? new EmailSetting;
    }

    public function save(Workspace $workspace, array $data): EmailSetting
    {
        return $workspace->emailSetting()->updateOrCreate([], $data);
    }
}
