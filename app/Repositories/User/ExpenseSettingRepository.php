<?php

namespace App\Repositories\User;

use App\Models\User\ExpenseSetting;
use App\Models\User\Workspace;

/** The expense_settings table: one row per business, once it first saves them. */
class ExpenseSettingRepository
{
    /** The business's settings - unsaved, with the defaults, if it has never saved them. */
    public function forWorkspace(Workspace $workspace): ExpenseSetting
    {
        return $workspace->expenseSetting()->firstOrNew();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function save(Workspace $workspace, array $data): ExpenseSetting
    {
        return $workspace->expenseSetting()->updateOrCreate([], $data);
    }
}
