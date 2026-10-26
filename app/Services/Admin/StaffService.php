<?php

namespace App\Services\Admin;

use App\Models\Admin\Role;
use App\Models\User\User;
use App\Repositories\Admin\RoleRepository;
use App\Repositories\Admin\StaffRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StaffService
{
    public function __construct(
        protected StaffRepository $staffRepo,
        protected RoleRepository $roleRepository,
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

        $updated = $this->staffRepo->update($user, $data);

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

        ActivityLogService::record('updated', "Toggled staff \"{$user->name}\" to {$status}", $user);

        return $user;
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
