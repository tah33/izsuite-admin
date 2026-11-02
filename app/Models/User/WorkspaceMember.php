<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone who belongs to a business (Doc 12 section 4), with the one role they
 * hold in it. Their account is theirs alone and can belong to other
 * businesses, each with its own role.
 *
 * Only an active member can open the business. Its owner is one, under
 * Business Owner.
 */
class WorkspaceMember extends Model
{
    public const ACTIVE = 'active';

    protected $fillable = [
        'user_id',
        'role_id',
        'status',
    ];

    protected $attributes = [
        'status' => self::ACTIVE,
    ];

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
}
