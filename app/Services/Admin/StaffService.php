<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\Admin\StaffRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StaffService
{
    public function __construct(
        protected StaffRepository $staffRepo,
    ) {}

    /**
     * Get filtered, paginated staff list.
     */
    public function getFilteredStaff(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->staffRepo->getPaginated($search, $perPage);
    }

    /**
     * Find staff user by ID.
     */
    public function find(int $id): ?User
    {
        return $this->staffRepo->find($id);
    }

    /**
     * Create a new staff user.
     */
    public function create(array $data): User
    {
        // password is auto-hashed by the User model's 'hashed' cast
        $data['status'] = 'active';

        return $this->staffRepo->create($data);
    }

    /**
     * Update a staff user.
     */
    public function update(User $user, array $data): User
    {
        if (! empty($data['password'])) {
            // password is auto-hashed by the User model's 'hashed' cast
        } else {
            unset($data['password']);
        }

        return $this->staffRepo->update($user, $data);
    }

    /**
     * Toggle staff user status.
     */
    public function toggleStatus(User $user): User
    {
        return $this->staffRepo->toggleStatus($user);
    }

    /**
     * Delete a staff user.
     */
    public function delete(User $user): ?bool
    {
        return $this->staffRepo->delete($user);
    }
}
