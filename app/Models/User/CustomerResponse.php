<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * What a customer did through a document's secure link (Doc 16 section
 * 16.22 - Phase G2): accepted or declined a quotation, or commented - with
 * the name they gave, what they wrote, and when and where from (their
 * address and browser).
 */
class CustomerResponse extends Model
{
    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const COMMENT = 'comment';

    protected $fillable = [
        'document_link_id',
        'linkable_type',
        'linkable_id',
        'kind',
        'name',
        'body',
        'ip_address',
        'user_agent',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(DocumentLink::class, 'document_link_id');
    }

    /** The document it was about. */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
