<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A document - or a payment reminder - emailed to a customer (Doc 14 section
 * 14.13): to whom, by whom and when, and whether the mail server took it.
 * Sent is not read: nothing here says the customer opened it.
 */
class DocumentEmail extends Model
{
    public const DOCUMENT = 'document';

    public const REMINDER = 'reminder';

    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = [
        'emailable_type',
        'emailable_id',
        'kind',
        'to',
        'cc',
        'subject',
        'status',
        'failure',
        'sent_by',
        'sent_at',
    ];

    protected $attributes = [
        'status' => self::QUEUED,
    ];

    protected $casts = [
        'cc'      => 'array',
        'sent_at' => 'datetime',
    ];

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The sales document or payment it carried. */
    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
