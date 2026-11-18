<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business's opportunity pipeline (Doc 17 section 17.13 - Phase A2): the
 * stages an opportunity moves through, in order. A business has one to
 * start; more come with opportunities (Phase D).
 */
class CrmPipeline extends Model
{
    public const STARTER_NAME = 'Sales Pipeline';

    /** The starting stages: [name, default probability, category]. */
    public const STARTER_STAGES = [
        ['New', 10, CrmStage::OPEN],
        ['Qualified', 25, CrmStage::OPEN],
        ['Proposal', 50, CrmStage::OPEN],
        ['Negotiation', 75, CrmStage::OPEN],
        ['Won', 100, CrmStage::WON],
        ['Lost', 0, CrmStage::LOST],
    ];

    protected $fillable = [
        'name',
        'position',
        'is_active',
        // Phase D3b: the sales teams and locations it is for - none, all of them.
        'team_ids',
        'location_ids',
    ];

    protected $casts = [
        'position'     => 'integer',
        'is_active'    => 'boolean',
        'team_ids'     => 'array',
        'location_ids' => 'array',
    ];

    // The columns' defaults, so one just made reads as it was saved.
    protected $attributes = [
        'position'  => 1,
        'is_active' => true,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Its stages, in order. */
    public function stages(): HasMany
    {
        return $this->hasMany(CrmStage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Whether an opportunity of $teamId's sales team at $locationId may be in
     * it (Phase D3b): it is for all teams, or that one among those chosen -
     * and likewise for locations. An opportunity without a team fits only a
     * pipeline for all teams; without a location, one for all locations.
     */
    public function fits(?int $teamId, ?int $locationId): bool
    {
        return ($this->team_ids === null || $this->team_ids === [] || ($teamId !== null && in_array($teamId, $this->team_ids, true)))
            && ($this->location_ids === null || $this->location_ids === [] || ($locationId !== null && in_array($locationId, $this->location_ids, true)));
    }
}
