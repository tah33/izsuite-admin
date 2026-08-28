<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Role;
use App\Models\User\User;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pipeline\Pipeline;

class StaffRepository
{
    public function getPaginated(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(
                User::with('role')
                    ->where('role_id', '!=', Role::SUPER_ADMIN_ID)
                    ->whereHas('role', fn ($q) => $q->whereNotIn('slug', ['recruiter', 'candidate']))
            )
            ->through([
                new SearchFilter($search, columns: ['name', 'email']),
            ])
            ->thenReturn()
            ->latest()
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function find(int $id): ?User
    {
        return User::with('role')->find($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh('role');
    }

    public function toggleStatus(User $user): User
    {
        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        return $user;
    }

    public function delete(User $user): ?bool
    {
        return $user->delete();
    }
}
