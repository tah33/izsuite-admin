<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a business's CRM lists (Doc 17 sections 17.8, 17.16 and 17.17
 * - Phase A1): a lead source, lost reason or activity type, in its order,
 * switched on or off. A starting one keeps izSuite's key for it, so the code
 * knows a call or an import whatever the business calls it; it is switched
 * off, never deleted.
 */
class CrmOption extends Model
{
    public const KINDS = ['lead_source' => 'Lead Sources', 'lost_reason' => 'Lost Reasons', 'activity_type' => 'Activity Types'];

    /** What each list starts with: Doc 17's examples, by izSuite's key. */
    public const STARTER = [
        'lead_source' => [
            'website' => 'Website', 'business_profile' => 'Digital Business Profile', 'social_media' => 'Social Media', 'email' => 'Email',
            'phone_call' => 'Phone Call', 'walk_in' => 'Walk-in', 'referral' => 'Referral', 'campaign' => 'Marketing Campaign',
            'partner' => 'Partner', 'event' => 'Event', 'import' => 'Import', 'other' => 'Other',
        ],
        'lost_reason' => [
            'price' => 'Price', 'no_budget' => 'No Budget', 'competitor' => 'Chose Competitor', 'no_response' => 'No Response',
            'not_ready' => 'Not Ready', 'not_suitable' => 'Product Not Suitable', 'duplicate' => 'Duplicate', 'invalid' => 'Invalid Lead', 'other' => 'Other',
        ],
        'activity_type' => [
            'call' => 'Call', 'meeting' => 'Meeting', 'task' => 'Task', 'email' => 'Email', 'follow_up' => 'Follow-up',
            'demonstration' => 'Demonstration', 'site_visit' => 'Site Visit', 'other' => 'Other',
        ],
    ];

    protected $fillable = [
        'kind',
        'key',
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
        'is_active' => true,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** One of izSuite's starting ones, which the code may know by its key. */
    public function isStarter(): bool
    {
        return $this->key !== null;
    }
}
