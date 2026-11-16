<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item or service a lead is interested in (Doc 17 section 17.6 - Phase
 * B2a): one of the business's items, its name kept as it was, or a name typed
 * in; with a quantity, when one was said.
 */
class CrmLeadItem extends Model
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
