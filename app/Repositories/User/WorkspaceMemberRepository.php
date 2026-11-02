<?php

namespace App\Repositories\User;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Models\User\WorkspaceRole;

class WorkspaceMemberRepository
{
    /** $user's membership of the business while it is active, with its role. */
    public function activeFor(Workspace $workspace, User $user): ?WorkspaceMember
    {
        return $workspace->members()->with('role')->where('user_id', $user->id)->where('status', WorkspaceMember::ACTIVE)->first();
    }

    /** $user as a member of the business under $role - made once, kept after. */
    public function firstOrCreateFor(Workspace $workspace, User $user, WorkspaceRole $role): WorkspaceMember
    {
        return $workspace->members()->firstOrCreate(['user_id' => $user->id], ['role_id' => $role->id]);
    }
}
