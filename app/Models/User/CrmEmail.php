<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An e-mail sent from a lead, an opportunity or a contact (Doc 17 section
 * 17.19 - Phase F3a): to whom, its subject and message as written, what was
 * attached - the record's sales documents, as PDFs made as it went, and its
 * files - and whether the mail server took it. It goes from the platform's
 * address under the business's sender name, with replies to the business,
 * and stays on the record's timeline. A lead's stay with the lead once it is
 * converted.
 */
class CrmEmail extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    /** What one can be sent from, by the column that holds it. */
    public const FOR = ['lead' => 'lead_id', 'opportunity' => 'opportunity_id', 'contact' => 'contact_id'];

    /** As many documents, and as many files, as one carries. */
    public const MAX_ATTACHED = 5;

    /** The files' size together, in bytes: what mail servers take, with room for the PDFs. */
    public const MAX_FILE_BYTES = 15 * 1024 * 1024;

    protected $fillable = [
        'lead_id',
        'opportunity_id',
        'contact_id',
        'to',
        'cc',
        'subject',
        'body',
        'attachments',
        'status',
        'failure',
        'sent_by',
        'sent_at',
    ];

    protected $attributes = [
        'status' => self::QUEUED,
    ];

    protected $casts = [
        'lead_id'        => 'integer',
        'opportunity_id' => 'integer',
        'contact_id'     => 'integer',
        'cc'             => 'array',
        'attachments'    => 'array',
        'sent_at'        => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** The record it was sent from. */
    public function regarding(): ?Model
    {
        return $this->opportunity ?? $this->lead ?? $this->contact;
    }
}
