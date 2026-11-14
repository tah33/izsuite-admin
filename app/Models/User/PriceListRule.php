<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rule of a price list (Doc 16 section 16.19): what it prices - an item,
 * an item category, a brand or everything - from what quantity up, between
 * which days, and how: a fixed price, a percentage off the item's sale price
 * (discount), or a percentage over its cost (markup).
 */
class PriceListRule extends Model
{
    public const TARGETS = ['item', 'category', 'brand', 'all'];

    public const METHODS = ['fixed', 'discount', 'markup'];

    protected $fillable = [
        'target',
        'item_id',
        'item_category_id',
        'item_brand_id',
        'min_quantity',
        'method',
        'value',
        'valid_from',
        'valid_to',
        'position',
    ];

    protected $casts = [
        'item_id'          => 'integer',
        'item_category_id' => 'integer',
        'item_brand_id'    => 'integer',
        'min_quantity'     => 'decimal:3',
        'value'            => 'decimal:4',
        'position'         => 'integer',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ItemBrand::class, 'item_brand_id');
    }
}
