<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A location of the business its storefronts' customers may collect their
 * orders from (Doc 19 sections 19.26 and 19.37 - Phase D1): switched on or
 * off, with its instructions and when an order is ready there. Pickup is
 * free. Its address and phone are its location's.
 */
class PickupPoint extends Model
{
    protected $fillable = [
        'location_id',
        'is_active',
        'instructions',
        'ready_within',
    ];

    protected $casts = [
        'location_id' => 'integer',
        'is_active'   => 'boolean',
    ];

    // The column's default, so one just made reads as it was saved.
    protected $attributes = [
        'is_active' => true,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
