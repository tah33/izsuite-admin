<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product or service one business sells or buys - the one record every app
 * that deals with it points at (Doc 01 section 10). Prices are in the
 * business's base currency.
 */
class Item extends Model
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_SERVICE = 'service';

    public const TYPES = [self::TYPE_PRODUCT, self::TYPE_SERVICE];

    protected $fillable = [
        'workspace_id',
        'type',
        'name',
        'sku',
        'unit',
        'sale_price',
        'purchase_price',
        'description',
        'is_active',
    ];

    /** The column defaults, so an item just made reads the same as one fetched. */
    protected $attributes = [
        'type'      => self::TYPE_PRODUCT,
        'is_active' => true,
    ];

    protected $casts = [
        'sale_price'     => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    // ── Relationships ──

    /** The business it belongs to. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
