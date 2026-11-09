<?php

namespace App\Repositories\Billing;

use App\Models\Billing\WorkspaceCard;
use App\Models\User\Workspace;

class WorkspaceCardRepository
{
    public function for(Workspace $workspace): ?WorkspaceCard
    {
        return WorkspaceCard::where('workspace_id', $workspace->id)->first();
    }

    /** The business's card row, created or changed. */
    public function save(Workspace $workspace, array $data): WorkspaceCard
    {
        return WorkspaceCard::updateOrCreate(['workspace_id' => $workspace->id], $data);
    }
}
