<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Someone who belongs to a business (Doc 12 sections 4 to 8), with the one
 * role they hold in it. Their account is theirs alone and can belong to other
 * businesses, each with its own role and locations.
 *
 * Invited, they are pending - no account yet (user_id null) or not accepted -
 * until the link in their e-mail is used: only its hash is kept, and it works
 * until invitation_expires_at. Only an active member can open the business;
 * suspended is blocked and can be let back in; removed has left, and is
 * invited again to return. Its owner is one, under Business Owner.
 *
 * Locations (section 20): all of the business's, or those listed, with the
 * one new records start at.
 */
class WorkspaceMember extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const REMOVED = 'removed';

    public const STATUSES = [self::PENDING, self::ACTIVE, self::SUSPENDED, self::REMOVED];

    /** Who counts toward the package's user limit: those who can open it, or are about to. */
    public const COUNTED = [self::PENDING, self::ACTIVE];

    public const ALL_LOCATIONS = 'all';

    public const SELECTED_LOCATIONS = 'selected';

    protected $fillable = [
        'user_id',
        'role_id',
        'name',
        'email',
        'phone',
        'job_title',
        'language',
        'timezone',
        'location_access',
        'default_location_id',
        'status',
        'invited_by',
        'invitation_token',
        'invitation_expires_at',
        'invited_at',
        'accepted_at',
        'suspended_at',
        'removed_at',
    ];

    protected $attributes = [
        'status'          => self::ACTIVE,
        'location_access' => self::ALL_LOCATIONS,
    ];

    protected $hidden = [
        'invitation_token',
    ];

    protected $casts = [
        'invitation_expires_at' => 'datetime',
        'invited_at'            => 'datetime',
        'accepted_at'           => 'datetime',
        'suspended_at'          => 'datetime',
        'removed_at'            => 'datetime',
    ];

    public function isOwnerOf(Workspace $workspace): bool
    {
        return $this->user_id !== null && $this->user_id === $workspace->user_id;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** Pending, with a link sent that still works. */
    public function invitationLive(): bool
    {
        return $this->isPending() && $this->invitation_expires_at?->isFuture();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(WorkspaceRole::class, 'role_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    /** The locations a member with selected access may use. */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'workspace_member_locations');
    }
}
