<?php

namespace App\Repositories\User;

use App\Models\User\User;
use App\Models\User\UserStaff;
use Illuminate\Database\Eloquent\Collection;

class UserStaffRepository
{
    /**
     * Alphabetical, with the id as tie-breaker so two staff members sharing a
     * name always come back in the same order.
     */
    public function getForUser(User $user): Collection
    {
        return UserStaff::where('user_id', $user->id)->orderBy('name')->orderBy('id')->get();
    }

    /**
     * Scoped to the owner: another account's id finds nothing, so callers
     * cannot tell "not yours" from "does not exist".
     */
    public function findForUser(User $user, int $id): ?UserStaff
    {
        return UserStaff::where('user_id', $user->id)->find($id);
    }

    /**
     * Created through the owner's relation, so user_id is always the caller's -
     * it is never read from $data.
     */
    public function create(User $user, array $data): UserStaff
    {
        return $user->userStaff()->create($data);
    }

    public function update(UserStaff $staff, array $data): UserStaff
    {
        $staff->update($data);

        return $staff;
    }

    public function delete(UserStaff $staff): void
    {
        $staff->delete();
    }
}
