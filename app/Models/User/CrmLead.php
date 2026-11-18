<?php

namespace App\Models\User;

use App\Models\Concerns\AtLocation;
use App\Models\Concerns\HasFiles;
use App\Models\Concerns\RecordsAuthors;
use App\Support\LeadMatchKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lead (Doc 17 sections 17.6 to 17.9): someone who may buy, before they are
 * a customer - who they are, where they came from, who follows them up, and
 * where they stand. New while nobody owns it (the queue); Assigned once
 * someone does; Contacted; Qualified or Unqualified (with a reason); and
 * Archived, which keeps it in history. Converted comes with Phase C.
 * Nothing about a lead is a sale. Its papers are its files, in the
 * "attachments" collection (Phase B2b).
 */
class CrmLead extends Model
{
    use AtLocation;
    use HasFiles;
    use RecordsAuthors;

    public const NEW = 'new';

    public const ASSIGNED = 'assigned';

    public const CONTACTED = 'contacted';

    public const QUALIFIED = 'qualified';

    public const UNQUALIFIED = 'unqualified';

    public const CONVERTED = 'converted';

    public const ARCHIVED = 'archived';

    public const STATUSES = [self::NEW, self::ASSIGNED, self::CONTACTED, self::QUALIFIED, self::UNQUALIFIED, self::CONVERTED, self::ARCHIVED];

    /** Still being worked: edited, assigned, contacted or qualified. */
    public const OPEN = [self::NEW, self::ASSIGNED, self::CONTACTED, self::QUALIFIED];

    /** Open and not contacted yet, awaiting follow-up: what the CRM Overview (Phase G1) and the Dashboard (Phase G4) count, and the Leads page lists. */
    public const AWAITING = [self::NEW, self::ASSIGNED];

    /** Closed without a sale, until reopened. */
    public const REOPENABLE = [self::UNQUALIFIED, self::ARCHIVED];

    public const PRIORITIES = ['low', 'medium', 'high'];

    public const ADDRESS_FIELDS = ['address_line1', 'address_line2', 'address_city', 'address_state', 'address_postal_code', 'address_country'];

    /** Its qualification (section 17.10 - Phase B2a), the interested items aside. */
    public const QUALIFICATION_FIELDS = ['customer_need', 'budget', 'purchase_period', 'decision_maker', 'business_size', 'next_action', 'qualification_notes'];

    /**
     * What Settings > CRM can require before a lead is qualified, with what a
     * refusal calls each - the interested items among them.
     */
    public const QUALIFYING = [
        'customer_need'       => 'the customer need',
        'interested_items'    => 'the interested items',
        'budget'              => 'the estimated budget',
        'purchase_period'     => 'the expected purchase period',
        'decision_maker'      => 'the decision maker',
        'business_size'       => 'the business size or type',
        'next_action'         => 'the next action',
        'qualification_notes' => 'the qualification notes',
    ];

    public const PURCHASE_PERIODS = ['this_month', 'within_3_months', 'within_6_months', 'within_12_months', 'later'];

    protected $fillable = [
        'name',
        'business_name',
        'job_title',
        'email',
        'phone',
        'source_id',
        'source_details',
        'status',
        'status_reason',
        'owner_id',
        'sales_team_id',
        'location_id',
        'priority',
        'estimated_value',
        'currency',
        'expected_purchase_date',
        ...self::ADDRESS_FIELDS,
        'preferred_language',
        'tags',
        'notes',
        ...self::QUALIFICATION_FIELDS,
        'assigned_at',
        'contacted_at',
        // When it was first given an owner and first answered (Phase G3a).
        'first_assigned_at',
        'first_contacted_at',
        // Its conversion (Phase C1).
        'contact_id',
        'opportunity_id',
        'converted_at',
        'converted_by',
    ];

    /** The column default, so a lead just made reads the same as one fetched. */
    protected $attributes = [
        'status' => self::NEW,
    ];

    protected $casts = [
        'source_id'              => 'integer',
        'owner_id'               => 'integer',
        'sales_team_id'          => 'integer',
        'location_id'            => 'integer',
        'estimated_value'        => 'decimal:2',
        'budget'                 => 'decimal:2',
        'expected_purchase_date' => 'date:Y-m-d',
        'tags'                   => 'array',
        'assigned_at'            => 'datetime',
        'contacted_at'           => 'datetime',
        'first_assigned_at'      => 'datetime',
        'first_contacted_at'     => 'datetime',
        'contact_id'             => 'integer',
        'opportunity_id'         => 'integer',
        'converted_at'           => 'datetime',
        'converted_by'           => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** One of Settings > CRM's lead sources (Doc 17 Phase A1). */
    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmOption::class, 'source_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class, 'sales_team_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** The items or services it is interested in (Phase B2a), in their order. */
    public function interestedItems(): HasMany
    {
        return $this->hasMany(CrmLeadItem::class)->orderBy('position')->orderBy('id');
    }

    /** The contact it was converted to (Phase C1). */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** The opportunity it was converted into (Phase C1). */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** What it is compared on for its possible duplicates (Phase C2), kept whenever it is saved. */
    protected static function booted(): void
    {
        static::saving(fn (self $lead) => $lead->forceFill(LeadMatchKeys::of($lead->email, $lead->phone, $lead->business_name)));
    }
}
