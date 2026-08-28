<?php

namespace App\Services\Admin;

use App\Models\Admin\Role;
use App\Repositories\Admin\RoleRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class RoleService
{
    public function __construct(
        protected RoleRepository $roleRepo,
    ) {}

    /**
     * Get all manageable roles.
     */
    public function getAll(): Collection
    {
        return $this->roleRepo->getAll();
    }

    /**
     * Find role by ID.
     */
    public function find(int $id): ?Role
    {
        return $this->roleRepo->find($id);
    }

    /**
     * Create a new role with auto-generated slug.
     */
    public function create(array $data): Role
    {
        $data['slug']        = Str::slug($data['name']);
        $data['permissions'] = $data['permissions'] ?? [];

        return $this->roleRepo->create($data);
    }

    /**
     * Update a role.
     */
    public function update(Role $role, array $data): Role
    {
        $data['slug']        = Str::slug($data['name']);
        $data['permissions'] = $data['permissions'] ?? [];

        return $this->roleRepo->update($role, $data);
    }

    /**
     * Delete a role if no users are assigned.
     */
    public function delete(Role $role): bool
    {
        return $this->roleRepo->delete($role);
    }
}
