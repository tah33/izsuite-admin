<?php

namespace App\Models\User;

use App\Models\Concerns\AtLocation;
use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An opportunity (Doc 17 section 17.12): a possible sale to a contact, at a
 * stage of the business's pipeline, with what it may be worth before tax
 * and when it may close. Phase C1 makes them by converting a lead, at an
 * open stage; their own pages, stage moves, won and lost come with Phase D.
 * Nothing about an opportunity is a sale.
 */
class CrmOpportunity extends Model
{
    use AtLocation;
    use RecordsAuthors;

    public const OPEN = 'open';

    public const WON = 'won';

    public const LOST = 'lost';

    protected $fillable = [
        'name',
        'contact_id',
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
        'notes',
        'stage_entered_at',
    ];

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
        'stage_entered_at'    => 'datetime',
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

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
