<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a business's lead scoring rules (Doc 17 section 17.22 - Phase I2):
 * the points - more or fewer - an open lead gets when it has what the rule's
 * factor asks for (LeadScoring):
 *
 * - source: it comes from the lead source source_id;
 * - location: it is at location_id;
 * - detail: it has the DETAILS option filled in;
 * - item: it is interested in item_id;
 * - value, budget: its estimated value or budget is at least threshold, in
 *   the base currency at today's rate;
 * - purchase_period: it expects to buy in the option, one of the lead's
 *   purchase periods;
 * - business_size: its business size or type has the option's words in it;
 * - engagement: at least threshold activities done and e-mails sent;
 * - response: it was contacted, or (late) it was not within Settings → CRM's
 *   lead response period;
 * - quiet: it has gone at least threshold days without activity.
 */
class CrmScoringRule extends Model
{
    public const FACTORS = ['source', 'location', 'detail', 'item', 'value', 'budget', 'purchase_period', 'business_size', 'engagement', 'response', 'quiet'];

    /** What a detail rule asks the lead to have filled in: its contact details' completeness, and more. */
    public const DETAILS = ['email', 'phone', 'business_name', 'job_title', 'address', 'decision_maker', 'customer_need', 'interested_items'];

    /** Contacted, or not contacted within the lead response period. */
    public const RESPONSES = ['contacted', 'late'];

    /** Which factor needs which field: the others are kept empty. */
    public const NEEDS = [
        'source'          => 'source_id',
        'location'        => 'location_id',
        'detail'          => 'option',
        'item'            => 'item_id',
        'value'           => 'threshold',
        'budget'          => 'threshold',
        'purchase_period' => 'option',
        'business_size'   => 'option',
        'engagement'      => 'threshold',
        'response'        => 'option',
        'quiet'           => 'threshold',
    ];

    protected $fillable = [
        'name',
        'is_active',
        'factor',
        'source_id',
        'location_id',
        'item_id',
        'option',
        'threshold',
        'points',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'source_id'   => 'integer',
        'location_id' => 'integer',
        'item_id'     => 'integer',
        'threshold'   => 'decimal:2',
        'points'      => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmOption::class, 'source_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
