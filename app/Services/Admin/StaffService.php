<?php

namespace App\Services\Admin;

use App\Models\Admin\Role;
use App\Models\User\User;
use App\Repositories\Admin\AdminSignInRepository;
use App\Repositories\Admin\RoleRepository;
use App\Repositories\Admin\StaffRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * The admin panel's staff. A member switched off, or given a new role or
 * password, has their sessions ended at once (Doc 15 section 15.2).
 */
class StaffService
{
    public function __construct(
        protected StaffRepository $staffRepo,
        protected RoleRepository $roleRepository,
        protected AdminSessionService $sessions,
        protected AdminSignInRepository $signIns,
        protected AdminTwoFactorService $twoFactor,
    ) {}

    /**
     * Get filtered, paginated staff list.
     */
    public function getFilteredStaff(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->staffRepo->getPaginated($search, $perPage);
    }

    /**
     * Roles a staff member can be given.
     */
    public function assignableRoles(): Collection
    {
        return $this->roleRepository->staffRoles();
    }

    /**
     * Find staff user by ID.
     */
    public function find(int $id): ?User
    {
        return $this->staffRepo->find($id);
    }

    /**
     * Find a staff user that may be changed: anyone but the Super Admin.
     */
    public function findEditable(int $id): ?User
    {
        $user = $this->find($id);

        return $user && ! $user->isSuperAdmin() ? $user : null;
    }

    /**
     * Create a new staff user.
     */
    public function create(array $data): User
    {
        // Prevent assigning Super Admin role
        abort_if((int) $data['role_id'] === Role::SUPER_ADMIN_ID, 403);

        // password is auto-hashed by the User model's 'hashed' cast
        $data['status'] = 'active';

        $staff = $this->staffRepo->create($data);

        // $staff->name is the composed accessor, so it reflects whatever
        // first/last name actually landed on the row.
        ActivityLogService::record('created', "Created staff member \"{$staff->name}\"", $staff);

        return $staff;
    }

    /**
     * Update a staff user.
     */
    public function update(User $user, array $data): User
    {
        // Prevent assigning Super Admin role
        abort_if((int) $data['role_id'] === Role::SUPER_ADMIN_ID, 403);

        if (! empty($data['password'])) {
            // password is auto-hashed by the User model's 'hashed' cast
        } else {
            unset($data['password']);
        }

        // A new role or password counts from now: their sessions end.
        $endSessions = (int) $data['role_id'] !== (int) $user->role_id || isset($data['password']);

        $updated = $this->staffRepo->update($user, $data);

        if ($endSessions) {
            $this->sessions->endAll($updated);
        }

        ActivityLogService::record('updated', "Updated staff member \"{$user->name}\"", $user);

        return $updated;
    }

    /**
     * Toggle staff user status.
     */
    public function toggleStatus(User $user): User
    {
        $user   = $this->staffRepo->toggleStatus($user);
        $status = $user->status === 'active' ? 'activated' : 'deactivated';

        // Switched off, they are signed out wherever they are.
        if ($user->status !== 'active') {
            $this->sessions->endAll($user);
        }

        ActivityLogService::record('updated', "Toggled staff \"{$user->name}\" to {$status}", $user);

        return $user;
    }

    /** Why the signed-in admin cannot switch $user off or on, if they cannot: nobody switches themselves off. */
    public function statusProblem(User $user): ?string
    {
        return $user->id === auth()->id() ? 'You cannot switch yourself off.' : null;
    }

    /** @return Collection<int, \App\Models\Admin\AdminSignIn> $user's latest sign-ins and failed attempts */
    public function recentSignIns(User $user): Collection
    {
        return $this->signIns->latestFor($user);
    }

    public function twoFactorIsOn(User $user): bool
    {
        return $this->twoFactor->isOn($user);
    }

    /** Resets $member's two-factor sign-in, by $admin, a Super Admin, with their password and a reason (AdminTwoFactorService::reset). */
    public function resetTwoFactor(User $admin, User $member, ?string $password, ?string $reason): void
    {
        $this->twoFactor->reset($admin, $member, $password, $reason);
    }

    /**
     * Delete a staff user.
     */
    public function delete(User $user): ?bool
    {
        $name    = $user->name;
        $deleted = $this->staffRepo->delete($user);

        ActivityLogService::record('deleted', "Deleted staff member \"{$name}\"");

        return $deleted;
    }

    /**
     * Why the signed-in admin cannot delete $user, if they cannot: nobody
     * deletes themselves.
     */
    public function deletionProblem(User $user): ?string
    {
        return $user->id === auth()->id() ? 'You cannot delete yourself.' : null;
    }
}
