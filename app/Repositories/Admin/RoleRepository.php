<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository
{
    public function getAll(): Collection
    {
        return Role::where('id', '>', Role::SUPER_ADMIN_ID)
            ->withCount('users')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?Role
    {
        return Role::withCount('users')->find($id);
    }

    public function findBySlug(string $slug): ?Role
    {
        return Role::where('slug', $slug)->first();
    }

    public function create(array $data): Role
    {
        return Role::create($data);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role->fresh();
    }

    public function delete(Role $role): bool
    {
        return $role->delete();
    }
}
