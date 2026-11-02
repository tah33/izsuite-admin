<?php

namespace App\Models\Shared;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Something done, by whom and when - in the business it belongs to, if any
 * (Settings > Security & Audit > Activity Log). properties may hold `changes`
 * (each setting's previous and new value, see SettingChanges), `fields` and
 * `location_id`.
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'properties',
        'ip_address',
    ];

    protected $casts    = [
        'properties' => 'array',
    ];

    /* -------------------------------------------------------
     | Relationships
     | ----------------------------------------------------- */

    /**
     * The user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The business it belongs to - none for what belongs to no business, a sign-in say. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The subject model (polymorphic).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }
}
