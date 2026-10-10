<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Builder;
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
        'name',
        'email',
        'phone',
        'address',
        'state',
        'country',
        'status',
    ];

    /**
     * A number in the database and a number in the API - never "1" - whatever
     * it arrived as.
     */
    protected $casts = [
        'status' => 'integer',
    ];

    // ── Scopes ──

    /**
     * The staff who can be given work, the only ones a workspace may be
     * attached to. One definition, so the check on an id sent back and the
     * dropdown it came from cannot disagree about who counts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
