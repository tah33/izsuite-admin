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

    /** What a lead needs filled in before it is qualified, unless the business chose (Phase B2a): the customer need. */
    public const QUALIFICATION_DEFAULT = ['customer_need'];

    /** The longest closing-soon period, in days (Phase G1). */
    public const MAX_CLOSING_SOON_DAYS = 365;

    protected $fillable = [
        'mode',
        'qualification_required',
        // Phase F2: confirming a sales order made from an opportunity wins it.
        'win_on_order',
        // Phase G1: an open opportunity expected to close within these days closes soon.
        'closing_soon_days',
    ];

    protected $attributes = [
        'mode'              => 'leads',
        'win_on_order'      => true,
        'closing_soon_days' => 30,
    ];

    protected $casts = [
        'qualification_required' => 'array',
        'win_on_order'           => 'boolean',
        'closing_soon_days'      => 'integer',
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

    /**
     * The keys of CrmLead::QUALIFYING a lead needs filled in before it is
     * qualified, in that order: the default until the business chooses, and
     * none when it chose none.
     *
     * @return list<string>
     */
    public function qualificationRequired(): array
    {
        $chosen = $this->qualification_required ?? self::QUALIFICATION_DEFAULT;

        return array_values(array_filter(array_keys(CrmLead::QUALIFYING), fn (string $key) => in_array($key, $chosen, true)));
    }
}
