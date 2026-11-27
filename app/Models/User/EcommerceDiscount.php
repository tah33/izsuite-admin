<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business's discount for its storefronts (Doc 19 section 19.31 - Phase
 * H1): a code its customers give at checkout - the business's name for it -
 * that takes off a percentage of the products, an amount (in the base
 * currency, as the business keeps prices) or the shipping; for the whole
 * order or chosen products (a listing's variants with it); from an order's
 * minimum (its products, in the base currency); between its first and last
 * days; used so many times in all and by one customer - none for no limit.
 * Its uses are its orders that are not cancelled.
 */
class EcommerceDiscount extends Model
{
    public const PERCENTAGE = 'percentage';

    public const AMOUNT = 'amount';

    public const FREE_SHIPPING = 'free_shipping';

    public const KINDS = [self::PERCENTAGE, self::AMOUNT, self::FREE_SHIPPING];

    /** What it is for: the whole order, or chosen products. */
    public const ORDER = 'order';

    public const PRODUCTS = 'products';

    protected $fillable = [
        'name',
        'code',
        'kind',
        'value',
        'applies_to',
        'minimum',
        'starts_on',
        'ends_on',
        'usage_limit',
        'per_customer_limit',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'value'              => 'decimal:4',
        'minimum'            => 'decimal:2',
        'starts_on'          => 'date:Y-m-d',
        'ends_on'            => 'date:Y-m-d',
        'usage_limit'        => 'integer',
        'per_customer_limit' => 'integer',
        'is_active'          => 'boolean',
        'created_by'         => 'integer',
    ];

    /**
     * Where it stands today - stateOn(), for its page - as the service that
     * read it sets it. Not a column: never saved.
     */
    public ?string $currentState = null;

    // The column's defaults, so one just made reads as it was saved.
    protected $attributes = [
        'applies_to' => self::ORDER,
        'is_active'  => true,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The listings it is for, when it is for chosen products. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(CatalogProduct::class, 'ecommerce_discount_products');
    }

    /** The orders placed with it - its uses, those not cancelled. */
    public function orders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class);
    }

    /** Where it stands on $today (the business's): inactive, not started, ended, used up - or active. $uses: its uses so far. */
    public function stateOn(string $today, int $uses): string
    {
        return match (true) {
            ! $this->is_active                                                     => 'inactive',
            $this->starts_on !== null && $this->starts_on->toDateString() > $today => 'scheduled',
            $this->ends_on !== null && $this->ends_on->toDateString() < $today     => 'ended',
            $this->usage_limit !== null && $uses >= $this->usage_limit             => 'used_up',
            default                                                                => 'active',
        };
    }
}
