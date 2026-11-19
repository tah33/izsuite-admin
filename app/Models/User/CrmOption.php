<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a business's CRM lists (Doc 17 sections 17.8, 17.16 and 17.17
 * - Phase A1): a lead source, lost reason, activity type or (Phase I3,
 * section 17.25) marketing consent purpose, in its order, switched on or
 * off. A starting one keeps izSuite's key for it, so the code knows a call
 * or an import whatever the business calls it; it is switched off, never
 * deleted.
 */
class CrmOption extends Model
{
    public const KINDS = ['lead_source' => 'Lead Sources', 'lost_reason' => 'Lost Reasons', 'activity_type' => 'Activity Types', 'consent_purpose' => 'Consent Purposes'];

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
        // Phase I3: what a contact or lead may agree to marketing about.
        'consent_purpose' => [
            'promotions' => 'Promotions and Offers', 'newsletters' => 'Newsletters', 'product_updates' => 'Product Updates',
            'events' => 'Events and Invitations', 'feedback' => 'Feedback and Surveys',
        ],
    ];

    /** The starting activity types whose completing needs an outcome (Doc 17 Phase E1) - the business can change each. */
    public const NEEDS_OUTCOME = ['call', 'meeting', 'demonstration', 'site_visit'];

    protected $fillable = [
        'kind',
        'key',
        'name',
        'position',
        'is_active',
        // An activity type's (Doc 17 Phase E1): completing one of it needs an outcome.
        'needs_outcome',
    ];

    protected $casts = [
        'position'  => 'integer',
        'is_active' => 'boolean',
        'needs_outcome' => 'boolean',
    ];

    // The columns' defaults, so one just made reads as it was saved.
    protected $attributes = [
        'is_active' => true,
        'needs_outcome' => false,
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
