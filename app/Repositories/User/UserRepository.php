<?php

namespace App\Repositories\User;

use App\Models\Admin\Role;
use App\Models\User\User;
use App\QueryFilters\SearchFilter;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;
use Laravel\Sanctum\NewAccessToken;

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
                new SearchFilter($search, columns: ['first_name', 'last_name', 'email']),
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

    /** Just the user - nothing loaded with it. */
    public function findById(?int $id): ?User
    {
        return User::find($id);
    }

    public function findWithRoleOrFail(int $id): User
    {
        return User::with('role')->findOrFail($id);
    }

    /** Every user with the role $roleSlug. */
    public function allWithRole(string $roleSlug): Collection
    {
        return User::whereHas('role', fn ($query) => $query->where('slug', $roleSlug))->get();
    }

    /** Everyone but the admins, by first name - who a plan can be given to. */
    public function nonAdmins(): Collection
    {
        return User::query()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->whereHas('role', fn ($query) => $query->whereNotIn('slug', ['super-admin', 'admin']))
            ->orderBy('first_name')
            ->get();
    }

    /** $user with its role, which the API's user payload shows. */
    public function loadRole(User $user): User
    {
        return $user->loadMissing('role');
    }

    public function findByEmail(string $email): ?User
    {
        return User::with('role')->where('email', strtolower($email))->first();
    }

    /** Like findByEmail, but with the email as given - the way Auth::attempt looks it up. */
    public function findByGivenEmail(string $email): ?User
    {
        return User::with('role')->where('email', $email)->first();
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

    public function delete(User $user): ?bool
    {
        return $user->delete();
    }

    public function updateLoginMeta(User $user, ?string $avatar = null): User
    {
        $user->forceFill([
            'last_login_at' => now(),
            'avatar'        => $user->avatar ?: $avatar,
        ])->save();

        return $user->fresh('role');
    }

    /** Sets $attributes, fillable or not, and saves. */
    public function forceUpdate(User $user, array $attributes): void
    {
        $user->forceFill($attributes)->save();
    }

    /**
     * Ends the user's API sessions - every one, or every one but $except
     * (the token the request came with). Returns how many ended.
     */
    public function revokeTokens(User $user, ?object $except = null): int
    {
        return $user->tokens()
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->delete();
    }

    /** A new API session for $user: its token, and the plain text the client keeps. */
    public function createToken(User $user, string $name, array $abilities, ?DateTimeInterface $expiresAt): NewAccessToken
    {
        return $user->createToken($name, $abilities, $expiresAt);
    }

    /** Ends the session the request came with. */
    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function touchLastLogin(User $user): void
    {
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();
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
