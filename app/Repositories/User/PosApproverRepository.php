<?php

namespace App\Repositories\User;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who might approve at a cashier's register (Doc 18 section 18.31 - Phase
 * I2): the business's owner and its active members who have a POS PIN, by
 * name - PosApprovals keeps those who hold what the approval takes.
 */
class PosApproverRepository
{
    public function candidates(Workspace $workspace): Collection
    {
        return User::query()
            ->whereNotNull('pos_pin')
            ->where(fn (Builder $query) => $query
                ->whereKey($workspace->user_id)
                ->orWhereIn('id', WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('status', WorkspaceMember::ACTIVE)->select('user_id')))
            ->orderBy('first_name')->orderBy('last_name')->orderBy('id')
            ->get();
    }

    public function find(int $id): ?User
    {
        return User::query()->find($id);
    }
}
