<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A price list (Doc 16 section 16.19): prices in one currency for the days it
 * holds, for all customers or those chosen, at all locations or those chosen.
 * When several lists hold for a line, the lowest priority number goes first.
 * Its rules (PriceListRule) say how it prices.
 */
class PriceList extends Model
{
    protected $fillable = [
        'name',
        'currency',
        'is_active',
        'valid_from',
        'valid_to',
        'all_customers',
        'all_locations',
        'priority',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'is_active'     => true,
        'all_customers' => true,
        'all_locations' => true,
        'priority'      => 10,
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'all_customers' => 'boolean',
        'all_locations' => 'boolean',
        'priority'      => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PriceListRule::class)->orderBy('position')->orderBy('id');
    }

    /** The customers it is for, unless all_customers. */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'price_list_contacts');
    }

    /** The locations it holds at, unless all_locations. */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'price_list_locations');
    }
}
