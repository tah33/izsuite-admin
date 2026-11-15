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
    ];

    protected $casts = [
        'position'  => 'integer',
        'is_active' => 'boolean',
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
}
