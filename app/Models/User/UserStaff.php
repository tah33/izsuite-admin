<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStaff extends Model
{
    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    /** The only values the status column is given. */
    public const STATUSES = [self::STATUS_INACTIVE, self::STATUS_ACTIVE];

    protected $fillable = [
        'user_id',
        'workspace_id',
        'name',
        'email',
        'phone',
        'address',
        'state',
        'country',
        'status',
        'assigned_apps',
        'assigned_workspaces',
    ];

    /**
     * A number in the database and a number in the API - never "1" - whatever
     * it arrived as. The two assignments are lists of ids, kept as JSON.
     */
    protected $casts = [
        'status'              => 'integer',
        'assigned_apps'       => 'array',
        'assigned_workspaces' => 'array',
    ];

    // ── Relationships ──

    /** The account that owns the business. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The business it belongs to. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
