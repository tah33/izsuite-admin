<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer a contact or lead gave about marketing (Doc 17 section 17.25 -
 * Phase I3): consent given or refused for one purpose (a Settings → CRM
 * list's entry) on one channel, where it came from, when (recorded_at), who
 * recorded it, when a consent given lapses, and its evidence or reference. A
 * consent given can be withdrawn: withdrawn_at, by whom and how, and its
 * status is then withdrawn. Nothing is deleted, so it is the history too:
 * for a purpose and channel, the latest answer is the one that stands.
 *
 * It is apart from sales follow-up: CRM's activities and e-mails to the same
 * person go on whatever it says.
 */
class MarketingConsent extends Model
{
    public const GIVEN = 'given';

    public const REFUSED = 'refused';

    public const WITHDRAWN = 'withdrawn';

    /** What an answer is when recorded; a consent given can be withdrawn later. */
    public const ANSWERS = [self::GIVEN, self::REFUSED];

    /** How the business may reach them. */
    public const CHANNELS = ['email', 'sms', 'messaging', 'phone'];

    /** Where the answer came from. */
    public const SOURCES = ['web_form', 'in_person', 'phone', 'email', 'paper', 'event', 'import', 'other'];

    protected $fillable = [
        'contact_id',
        'lead_id',
        'purpose_id',
        'channel',
        'status',
        'source',
        'recorded_at',
        'recorded_by',
        'expires_on',
        'evidence',
        'withdrawn_at',
        'withdrawn_by',
        'withdrawal_note',
    ];

    protected $casts = [
        'contact_id'   => 'integer',
        'lead_id'      => 'integer',
        'purpose_id'   => 'integer',
        'recorded_at'  => 'datetime',
        'recorded_by'  => 'integer',
        'expires_on'   => 'date:Y-m-d',
        'withdrawn_at' => 'datetime',
        'withdrawn_by' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    /** One of Settings → CRM's consent purposes. */
    public function purpose(): BelongsTo
    {
        return $this->belongsTo(CrmOption::class, 'purpose_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function withdrawer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }
}
