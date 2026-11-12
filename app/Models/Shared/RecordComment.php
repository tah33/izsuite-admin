<?php

namespace App\Models\Shared;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A comment on a sales document, purchase or expense (Doc 14 section 14.14 -
 * Phase D). It is written by one user of the business, for the business
 * alone, and never printed. `mentions` keeps the users it mentions as they
 * were named then: [{id, name}].
 */
class RecordComment extends Model
{
    protected $fillable = [
        'workspace_id',
        'commentable_type',
        'commentable_id',
        'user_id',
        'body',
        'mentions',
    ];

    protected $casts = [
        'mentions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The sales document, purchase or expense it is on. */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
