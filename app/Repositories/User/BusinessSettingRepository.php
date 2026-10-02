<?php

namespace App\Repositories\User;

use App\Models\User\BusinessSetting;
use App\Models\User\User;

class BusinessSettingRepository
{
    public function findForUser(User $user): ?BusinessSetting
    {
        return BusinessSetting::where('user_id', $user->id)->first();
    }

    /**
     * One row per account (user_id is unique), so saving is "write over it, or
     * create it the first time" - never a second row.
     */
    public function save(User $user, array $data): BusinessSetting
    {
        return BusinessSetting::updateOrCreate(['user_id' => $user->id], $data);
    }
}
