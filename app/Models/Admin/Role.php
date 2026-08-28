<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /**
     * Super Admin role ID — bypasses all permission checks.
     * This role is hidden from UI lists and cannot be deleted or reassigned.
     */
    public const SUPER_ADMIN_ID = 1;

    protected $fillable         = [
        'name',
        'slug',
        'permissions',
    ];

    protected $casts            = [
        'permissions' => 'array',
    ];

    /* -------------------------------------------------------
     | Relationships
     | ----------------------------------------------------- */

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /* -------------------------------------------------------
     | Helpers
     | ----------------------------------------------------- */

    /**
     * Check if this role has a specific permission (by route name).
     */
    public function hasPermission(string $routeName): bool
    {
        if ($this->id === self::SUPER_ADMIN_ID) {
            return true;
        }

        return in_array($routeName, $this->permissions ?? []);
    }

    /**
     * Is this the protected Super Admin role?
     */
    public function isSuperAdmin(): bool
    {
        return $this->id === self::SUPER_ADMIN_ID;
    }
}
