<?php

namespace App\Models\User;

use App\Models\Concerns\AtLocation;
use App\Models\Concerns\HasFiles;
use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An opportunity (Doc 17 section 17.12): a possible sale to a contact, at a
 * stage of the business's pipeline, with what it may be worth before tax,
 * how likely it is and when it may close. Phase C1 makes them by converting
 * a lead; Phase D1 adds them by hand, changes them and moves them from stage
 * to stage, each stage it is at kept (see CrmStageVisit). Phase D2a wins,
 * loses, reopens and archives them: how one ended is kept on it until it is
 * reopened, and an archived one no longer changes until it is restored.
 * Phase D2b gives it files in the "attachments" collection, comments and a
 * timeline. Nothing about an opportunity is a sale.
 */
class CrmOpportunity extends Model
{
    use AtLocation;
    use HasFiles;
    use RecordsAuthors;

    public const OPEN = 'open';

    public const WON = 'won';

    public const LOST = 'lost';

    public const STATUSES = [self::OPEN, self::WON, self::LOST];

    /**
     * The open ones a list can be narrowed to: closing soon (Phase G4, what
     * the Dashboard counts), stale at their stage (Phase D3a), with no
     * activity planned, or closing soon without recent activity (Phase G3b) -
     * what the CRM Overview warns about.
     */
    public const FOLLOW_UP_STATUSES = ['closing_soon', 'stale', 'no_activity', 'quiet'];

    /** As many competitors as one can name. */
    public const MAX_COMPETITORS = 10;

    protected $fillable = [
        'name',
        'contact_id',
        'contact_person',
        'lead_id',
        'pipeline_id',
        'stage_id',
        'status',
        'probability',
        'expected_value',
        'currency',
        'expected_close_date',
        'owner_id',
        'location_id',
        'sales_team_id',
        'source_id',
        'priority',
        'requirements',
        'next_action',
        'competitors',
        'tags',
        'notes',
        'stage_entered_at',
        // How it ended (Phase D2a), and archiving it.
        'closed_on',
        'closed_by',
        'won_reason',
        'lost_reason_id',
        'lost_competitor',
        'lost_notes',
        'follow_up_on',
        'archived_at',
        'archived_by',
    ];

    /** What reopening clears: how it ended. */
    public const OUTCOME_FIELDS = ['closed_on', 'closed_by', 'won_reason', 'lost_reason_id', 'lost_competitor', 'lost_notes', 'follow_up_on'];

    /** The column default, so an opportunity just made reads the same as one fetched. */
    protected $attributes = [
        'status' => self::OPEN,
    ];

    protected $casts = [
        'contact_id'          => 'integer',
        'lead_id'             => 'integer',
        'pipeline_id'         => 'integer',
        'stage_id'            => 'integer',
        'probability'         => 'integer',
        'expected_value'      => 'decimal:2',
        'expected_close_date' => 'date:Y-m-d',
        'owner_id'            => 'integer',
        'location_id'         => 'integer',
        'sales_team_id'       => 'integer',
        'source_id'           => 'integer',
        'competitors'         => 'array',
        'tags'                => 'array',
        'stage_entered_at'    => 'datetime',
        'closed_on'           => 'date:Y-m-d',
        'closed_by'           => 'integer',
        'lost_reason_id'      => 'integer',
        'follow_up_on'        => 'date:Y-m-d',
        'archived_at'         => 'datetime',
        'archived_by'         => 'integer',
    ];

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

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

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class, 'sales_team_id');
    }

    /** The lead source it came from (Settings → CRM's list). */
    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmOption::class, 'source_id');
    }

    /** Who marked it won or lost (Phase D2a). */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Why it was lost: Settings → CRM's list. */
    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(CrmOption::class, 'lost_reason_id');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /** What it is for (Phase D1), in their order. */
    public function interestedItems(): HasMany
    {
        return $this->hasMany(CrmOpportunityItem::class)->orderBy('position')->orderBy('id');
    }

    /** Each stage it has been at, the first first; the one with no left_at is where it is now. */
    public function stageVisits(): HasMany
    {
        return $this->hasMany(CrmStageVisit::class)->orderBy('entered_at')->orderBy('id');
    }
}
