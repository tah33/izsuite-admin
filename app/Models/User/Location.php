<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A branch, shop, office or warehouse of one business (workspace).
 */
class Location extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_id',
        'country',
        'state',
        'name',
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
