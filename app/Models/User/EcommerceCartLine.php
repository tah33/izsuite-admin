<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product in a cart (Doc 19 Phase D3): its listing, its variant - one of
 * the listing's, when it has them - and the item sold, with its quantity and
 * its price as last shown. Taken out of the catalog, a listing or variant
 * takes its lines with it.
 */
class EcommerceCartLine extends Model
{
    protected $fillable = [
        'catalog_product_id',
        'catalog_product_variant_id',
        'item_id',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'catalog_product_id'         => 'integer',
        'catalog_product_variant_id' => 'integer',
        'item_id'                    => 'integer',
        'quantity'                   => 'decimal:3',
        'unit_price'                 => 'decimal:2',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcommerceCart::class, 'ecommerce_cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogProductVariant::class, 'catalog_product_variant_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
