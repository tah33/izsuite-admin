<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A secure link to one sales document (Doc 06 section 9, Doc 08 section 8):
 * whoever has it sees the document and downloads its PDF, without signing in
 * - nothing else, and never the business's internal notes or numbers.
 *
 * It works until it is revoked, or its document is voided or cancelled. Each
 * view is counted.
 */
class DocumentLink extends Model
{
    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'token',
        'created_by',
        'revoked_at',
        'views',
        'last_viewed_at',
    ];

    protected $attributes = [
        'views' => 0,
    ];

    protected $casts = [
        'revoked_at'     => 'datetime',
        'views'          => 'integer',
        'last_viewed_at' => 'datetime',
    ];

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The sales document it opens. */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
