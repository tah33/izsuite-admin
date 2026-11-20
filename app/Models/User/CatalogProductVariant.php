<?php

namespace App\Models\User;

use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A variant of an online listing (Doc 19 section 19.10 - Phase B3): an
 * existing item of the business, sold under the listing as one of its
 * options' combinations - Size M, Colour Red. Its SKU, barcode, price and
 * image are its item's; here are its options' values, in the listing's
 * order, whether storefronts show it, its availability without Inventory,
 * and its weight (none: the listing's). A listing's own item is its first
 * variant. An item is a variant of one listing at most.
 */
class CatalogProductVariant extends Model
{
    use RecordsAuthors;

    public const ACTIVE = 'active';

    public const STATUSES = [self::ACTIVE, 'hidden'];

    protected $fillable = [
        'item_id',
        'option_values',
        'status',
        'availability',
        'stock_quantity',
        'weight',
        'position',
    ];

    protected $casts = [
        'catalog_product_id' => 'integer',
        'item_id'            => 'integer',
        'option_values'      => 'array',
        'stock_quantity'     => 'decimal:3',
        'weight'             => 'decimal:3',
        'position'           => 'integer',
    ];

    protected $attributes = [
        'status'       => self::ACTIVE,
        'availability' => CatalogProduct::AVAILABLE,
        'position'     => 0,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
