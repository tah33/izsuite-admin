<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A business's opportunity pipeline (Doc 17 section 17.13 - Phase A2): the
 * stages an opportunity moves through, in order. A business has one to
 * start, and (Phase D3b1) up to MAX: one is the default, where new
 * opportunities start - always switched on. One switched off takes no
 * opportunity in, and those in it carry on there.
 */
class CrmPipeline extends Model
{
    public const STARTER_NAME = 'Sales Pipeline';

    public const MAX = 20;

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
        'is_default',
    ];

    protected $casts = [
        'position'   => 'integer',
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    // The columns' defaults, so one just made reads as it was saved.
    protected $attributes = [
        'position'   => 1,
        'is_active'  => true,
        'is_default' => false,
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

    /** The opportunities in it now. */
    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'pipeline_id');
    }

    /** Every time an opportunity came to one of its stages - those since moved to another pipeline too. */
    public function visits(): HasManyThrough
    {
        return $this->hasManyThrough(CrmStageVisit::class, CrmStage::class, 'crm_pipeline_id', 'crm_stage_id');
    }
}
