<?php

namespace App\Repositories\User;

use App\Models\User\Workspace;
use App\Models\User\WorkspaceBilling;

class WorkspaceBillingRepository
{
    /** The business's billing details - none until first saved. */
    public function for(Workspace $workspace): ?WorkspaceBilling
    {
        return $workspace->billing()->first();
    }

    /** Saves the business's billing details, making them on the first save. */
    public function save(Workspace $workspace, array $details): WorkspaceBilling
    {
        return $workspace->billing()->updateOrCreate([], $details);
    }
}
