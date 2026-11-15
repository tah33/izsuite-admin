<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Settings > CRM (Doc 17 section 17.26 - Phase A1): how the business uses
 * CRM. A business has none until it first saves them; an unsaved one reads
 * the defaults, the same as the columns'.
 */
class CrmSetting extends Model
{
    /** Leads qualified and converted into opportunities, or opportunities made directly (section 17.5). */
    public const MODES = ['leads' => 'Leads and Opportunities', 'opportunities' => 'Opportunities Only'];

    protected $fillable = [
        'mode',
    ];

    protected $attributes = [
        'mode' => 'leads',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Whether the business captures leads before opportunities. */
    public function usesLeads(): bool
    {
        return $this->mode === 'leads';
    }
}
