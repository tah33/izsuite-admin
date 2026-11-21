<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where the business ships (Doc 19 section 19.26 - Phase D1): its countries,
 * narrowed if wished to regions, cities and postal codes - a code, or its
 * start followed by "*" - each list empty for every one. The zones are kept
 * in an order: an address takes the methods of the first zone it matches
 * (ShippingRates).
 */
class ShippingZone extends Model
{
    /** How many places each of its lists names at most. */
    public const MAX_PLACES = 200;

    protected $fillable = [
        'name',
        'countries',
        'regions',
        'cities',
        'postal_codes',
        'position',
    ];

    protected $casts = [
        'countries'    => 'array',
        'regions'      => 'array',
        'cities'       => 'array',
        'postal_codes' => 'array',
        'position'     => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Its ways of shipping, in their order. */
    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class)->orderBy('position')->orderBy('id');
    }
}
