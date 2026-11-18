<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stage an opportunity was at (Doc 17 section 17.13 - Phase D1): when it
 * came and who moved it there, and when it left - none while it is still
 * there. The time between is the time it spent at the stage.
 */
class CrmStageVisit extends Model
{
    protected $fillable = [
        'crm_stage_id',
        'entered_at',
        'left_at',
        'entered_by',
    ];

    protected $casts = [
        'crm_stage_id' => 'integer',
        'entered_at'   => 'datetime',
        'left_at'      => 'datetime',
        'entered_by'   => 'integer',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'crm_opportunity_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'crm_stage_id');
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /** How long it was at the stage - or has been, while it is there - in seconds. */
    public function seconds(): int
    {
        return (int) max(0, $this->entered_at->diffInSeconds($this->left_at ?? now(), false));
    }
}
