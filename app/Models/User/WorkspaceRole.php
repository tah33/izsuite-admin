<?php

namespace App\Models\User;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of a business's roles (Doc 12 sections 10 and 11): what its members who
 * hold it may do - its permissions (Permissions), or every one there is with
 * full access, apps turned on later included.
 *
 * `template` says which default it started as; null is a role the business
 * added. Business Owner (template "owner") is protected: it cannot be changed,
 * switched off or deleted. Other templates can be changed and switched off,
 * but only a role the business added can be deleted - and none while someone
 * holds it.
 */
class WorkspaceRole extends Model
{
    public const OWNER = 'owner';

    protected $fillable = [
        'template',
        'name',
        'description',
        'full_access',
        // The largest discount it gives, as a percentage - null for no limit (Doc 16 Phase F2).
        'max_discount',
        'permissions',
        'is_active',
    ];

    protected $attributes = [
        'full_access' => false,
        'is_active'   => true,
    ];

    protected $casts = [
        'full_access' => 'boolean',
        'max_discount' => 'decimal:2',
        'permissions' => 'array',
        'is_active'   => 'boolean',
    ];

    public function isOwner(): bool
    {
        return $this->template === self::OWNER;
    }

    /** One of the defaults (Doc 12 section 11), rather than one the business added. */
    public function isTemplate(): bool
    {
        return $this->template !== null;
    }

    /** @return list<string> ALL for full access */
    public function granted(): array
    {
        return $this->full_access ? [Permissions::ALL] : ($this->permissions ?? []);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'role_id');
    }
}
