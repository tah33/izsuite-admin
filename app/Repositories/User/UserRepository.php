<?php

namespace App\Repositories\User;

use App\Models\Admin\Role;
use App\Models\User\User;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class UserRepository
{
    public function getTotalCount(): int
    {
        return $this->frontendUsersQuery()->count();
    }

    public function getActiveCount(): int
    {
        return $this->frontendUsersQuery()
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->subDays(30))
            ->count();
    }

    public function getGrowthHistory(int $months = 6): array
    {
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date   = now()->subMonths($i);
            $data[] = [
                'label' => $date->format('M'),
                'value' => $this->frontendUsersQuery()
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count(),
            ];
        }

        return $data;
    }

    public function getPaginated(string $roleSlug, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(
                User::query()
                    ->where('role_id', '!=', Role::SUPER_ADMIN_ID)
                    ->with(['role', 'currentPlanSubscription'])
                    ->withSum(['invoices as total_paid' => fn ($q) => $q->where('status', 'paid')], 'amount')
                    ->whereHas('role', fn (Builder $query) => $query->where('slug', $roleSlug))
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
        return User::with(['role', 'currentPlanSubscription'])->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::with('role')->where('email', strtolower($email))->first();
    }

    public function emailExists(string $email): bool
    {
        return User::where('email', strtolower($email))->exists();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh(['role']);
    }

    public function updateLoginMeta(User $user, ?string $avatar = null): User
    {
        $user->forceFill([
            'last_login_at' => now(),
            'avatar'        => $user->avatar ?: $avatar,
        ])->save();

        return $user->fresh('role');
    }

    public function toggleStatus(User $user): User
    {
        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        return $user;
    }

    /**
     * Users that are not platform staff (admins).
     * Kept generic so OverviewService stats stay valid after the
     * recruitment backend was removed.
     */
    protected function frontendUsersQuery(): Builder
    {
        return User::query()->where('role_id', '!=', Role::SUPER_ADMIN_ID);
    }
}
