<?php

namespace App\Models\Shared;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A notification for one user in one business (Doc 14 section 15 - Phase
 * B1): what happened, the page that shows it, and how it was delivered. One
 * per user and event (`event_key`).
 */
class UserNotification extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'trigger',
        'title',
        'body',
        'link',
        // Whether it is in the bell: false when it went by e-mail only (Phase B2).
        'shown',
        'subject_type',
        'subject_id',
        'event_key',
        'read_at',
    ];

    protected $casts    = [
        'shown'   => 'boolean',
        'read_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
