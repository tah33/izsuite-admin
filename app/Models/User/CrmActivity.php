<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A CRM activity (Doc 17 section 17.17 - Phase E1): a call, meeting, task or
 * any other of the business's activity types, for a lead, an opportunity or
 * a contact, given to a member and due at a day and time. It is planned,
 * then completed - with an outcome where its type needs one, and perhaps a
 * follow-up planned straight after - or cancelled; both are final. A planned
 * one is overdue once its time has passed.
 *
 * A lead's activities go with it into its opportunity on conversion, keeping
 * the lead too. Its location is its record's, for who may see it; a
 * contact's has none.
 */
class CrmActivity extends Model
{
    public const PLANNED = 'planned';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::PLANNED, self::COMPLETED, self::CANCELLED];

    public const PRIORITIES = ['low', 'medium', 'high'];

    /** A call's direction: the activity type izSuite knows as a call (key "call") takes one. */
    public const DIRECTIONS = ['inbound', 'outbound'];

    /** What an activity can be for, by the column that holds it. */
    public const FOR = ['lead' => 'lead_id', 'opportunity' => 'opportunity_id', 'contact' => 'contact_id'];

    protected $fillable = [
        'type_id',
        'subject',
        'lead_id',
        'opportunity_id',
        'contact_id',
        'location_id',
        'assigned_to',
        'due_at',
        'duration_minutes',
        'priority',
        'place',
        'call_direction',
        'description',
        'status',
        'outcome',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'follow_up_of',
        'created_by',
    ];

    protected $casts = [
        'type_id'          => 'integer',
        'lead_id'          => 'integer',
        'opportunity_id'   => 'integer',
        'contact_id'       => 'integer',
        'location_id'      => 'integer',
        'assigned_to'      => 'integer',
        'due_at'           => 'datetime',
        'duration_minutes' => 'integer',
        'completed_at'     => 'datetime',
        'cancelled_at'     => 'datetime',
        'follow_up_of'     => 'integer',
    ];

    // The column's default, so one just made reads as it was saved.
    protected $attributes = [
        'status' => self::PLANNED,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CrmOption::class, 'type_id');
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The activity it follows up. */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'follow_up_of');
    }

    /** The activity planned to follow it up, if any. */
    public function followUp(): HasOne
    {
        return $this->hasOne(self::class, 'follow_up_of');
    }

    public function isPlanned(): bool
    {
        return $this->status === self::PLANNED;
    }

    /** The record it is for now: its opportunity, once a lead's went into one, else its lead or contact. */
    public function regarding(): ?Model
    {
        return $this->opportunity ?? $this->lead ?? $this->contact;
    }

    /** "opportunity", "lead" or "contact": what regarding() is. */
    public function regardingKind(): ?string
    {
        return $this->opportunity_id ? 'opportunity' : ($this->lead_id ? 'lead' : ($this->contact_id ? 'contact' : null));
    }
}
