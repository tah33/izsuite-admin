<?php

namespace App\Models\Shared;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
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

    /**
     * The subject model (polymorphic).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }
}
