<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A decision about one of a lead's possible duplicates (Doc 17 section 17.21
 * - Phase C2): another lead or a contact, ignored with a reason - no longer
 * flagged for that match - or linked to it. Between two leads it is kept on
 * both.
 */
class CrmDuplicateReview extends Model
{
    public const LEAD = 'lead';

    public const CONTACT = 'contact';

    public const TYPES = [self::LEAD, self::CONTACT];

    public const IGNORED = 'ignored';

    public const LINKED = 'linked';

    protected $fillable = [
        'workspace_id',
        'crm_lead_id',
        'other_type',
        'other_id',
        'decision',
        'reason',
        'decided_by',
    ];

    protected $casts = [
        'crm_lead_id' => 'integer',
        'other_id'    => 'integer',
        'decided_by'  => 'integer',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
