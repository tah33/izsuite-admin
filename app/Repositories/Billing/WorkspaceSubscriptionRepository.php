<?php

namespace App\Repositories\Billing;

use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\Workspace;

class WorkspaceSubscriptionRepository
{
    /** The business's subscription, with its plan - null if it never had one. */
    public function for(Workspace $workspace): ?WorkspaceSubscription
    {
        return $workspace->subscription()->with('package')->first();
    }

    /** Makes or changes the business's one subscription. */
    public function save(Workspace $workspace, array $data): WorkspaceSubscription
    {
        return $workspace->subscription()->updateOrCreate([], $data)->load('package');
    }
}
