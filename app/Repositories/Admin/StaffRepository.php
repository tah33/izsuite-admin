<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Role;
use App\Models\User\User;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class StaffRepository
{
    public function getPaginated(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(
                $this->staff()
                    ->with('role')
                    ->where('role_id', '!=', Role::SUPER_ADMIN_ID)
            )
            ->through([
                new SearchFilter($search, columns: ['first_name', 'last_name', 'email']),
            ])
            ->thenReturn()
            ->latest()
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /** A staff member; a client or any other non-staff account is not found. */
    public function find(int $id): ?User
    {
        return $this->staff()->with('role')->find($id);
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

    /** Admin staff only: accounts whose role is not one of Role::FRONTEND_SLUGS. */
    private function staff(): Builder
    {
        return User::query()->whereHas('role', fn (Builder $q) => $q->whereNotIn('slug', Role::FRONTEND_SLUGS));
    }
}
