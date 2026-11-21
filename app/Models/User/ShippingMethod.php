<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way a zone ships (Doc 19 section 19.26 - Phase D1):
 *
 * - a flat rate, or local delivery by the business itself: its price;
 * - free;
 * - by weight, by order value or by quantity: its rates - bands, each from a
 *   weight (kg), an order value or a number of items, at its price - the
 *   band an order reaches being the highest it gets to;
 * - quoted after the order: no price yet.
 *
 * Prices and order values are in the base currency, as items' prices are.
 * Its tax is a sales tax rate, or none. It is offered for orders within its
 * order values and weights, carrying products of its categories only when it
 * names some, while it is on. Carriers' own rates wait for their
 * integrations.
 */
class ShippingMethod extends Model
{
    public const FLAT = 'flat';

    public const FREE = 'free';

    public const WEIGHT = 'weight';

    public const ORDER_VALUE = 'order_value';

    public const QUANTITY = 'quantity';

    public const LOCAL_DELIVERY = 'local_delivery';

    public const QUOTATION = 'quotation';

    public const KINDS = [self::FLAT, self::FREE, self::WEIGHT, self::ORDER_VALUE, self::QUANTITY, self::LOCAL_DELIVERY, self::QUOTATION];

    /** The kinds priced by bands. */
    public const BANDED = [self::WEIGHT, self::ORDER_VALUE, self::QUANTITY];

    /** The kinds with a price of their own. */
    public const PRICED = [self::FLAT, self::LOCAL_DELIVERY];

    /** How many bands a method has at most. */
    public const MAX_RATES = 20;

    protected $fillable = [
        'shipping_zone_id',
        'name',
        'kind',
        'price',
        'rates',
        'tax_rate_id',
        'days_min',
        'days_max',
        'min_order',
        'max_order',
        'min_weight',
        'max_weight',
        'category_ids',
        'is_active',
        'position',
    ];

    protected $casts = [
        'shipping_zone_id' => 'integer',
        'price'            => 'decimal:2',
        'rates'            => 'array',
        'tax_rate_id'      => 'integer',
        'days_min'         => 'integer',
        'days_max'         => 'integer',
        'min_order'        => 'decimal:2',
        'max_order'        => 'decimal:2',
        'min_weight'       => 'decimal:3',
        'max_weight'       => 'decimal:3',
        'category_ids'     => 'array',
        'is_active'        => 'boolean',
        'position'         => 'integer',
    ];

    // The column's default, so one just made reads as it was saved.
    protected $attributes = [
        'is_active' => true,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
