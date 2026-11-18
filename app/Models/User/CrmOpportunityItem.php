<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item or service an opportunity is for (Doc 17 section 17.12 - Phase D1),
 * as a lead's are (see CrmLeadItem): one of the business's items, its name
 * kept as it was, or a name typed in, with a quantity when one was said.
 */
class CrmOpportunityItem extends Model
{
    protected $fillable = [
        'item_id',
        'name',
        'quantity',
        'position',
    ];

    protected $casts = [
        'item_id'  => 'integer',
        'quantity' => 'decimal:3',
        'position' => 'integer',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'crm_opportunity_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
