<?php

namespace App\Repositories\Billing;

use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceSubscriptionRepository
{
    /** The business's subscription, with its plan and any it moves to - null if it never had one. */
    public function for(Workspace $workspace): ?WorkspaceSubscription
    {
        return $workspace->subscription()->with(['package', 'scheduledPackage'])->first();
    }

    /** Makes or changes the business's one subscription. */
    public function save(Workspace $workspace, array $data): WorkspaceSubscription
    {
        return $workspace->subscription()->updateOrCreate([], $data)->load(['package', 'scheduledPackage']);
    }

    /** Every subscription the daily cycle moves on - on a trial, active or in grace - with its business, owner and plans. */
    public function running(): Collection
    {
        return WorkspaceSubscription::with(['workspace.user', 'package', 'scheduledPackage'])->whereIn('status', WorkspaceSubscription::RUNNING)->orderBy('id')->get();
    }
}
