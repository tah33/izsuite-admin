<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of an online order (Doc 19 Phase D4): a product bought - its
 * listing, variant and item, kept by name, options and SKU as the store
 * showed them - or the order's shipping. Its price as the business keeps
 * prices and where it came from (the item, or the storefront's price list),
 * its tax as it was, and its amounts - and as the store showed them. A line
 * of a product to deliver is marked shipped, with its weight (kg).
 */
class EcommerceOrderLine extends Model
{
    public const PRODUCT = 'product';

    public const SHIPPING = 'shipping';

    protected $fillable = [
        'kind',
        'catalog_product_id',
        'catalog_product_variant_id',
        'item_id',
        'title',
        'options',
        'sku',
        'name',
        'unit',
        'quantity',
        'unit_price',
        'price_source',
        'price_list_id',
        'price_list_rule_id',
        'tax_rate_id',
        'tax_name',
        'tax_code',
        'tax_rate',
        'tax_treatment',
        'amount',
        'tax_amount',
        'shown_unit_price',
        'shown_amount',
        'weight',
        'shipped',
        'position',
    ];

    protected $casts = [
        'catalog_product_id'         => 'integer',
        'catalog_product_variant_id' => 'integer',
        'item_id'                    => 'integer',
        'options'                    => 'array',
        'quantity'                   => 'decimal:3',
        'unit_price'                 => 'decimal:2',
        'price_list_id'              => 'integer',
        'price_list_rule_id'         => 'integer',
        'tax_rate_id'                => 'integer',
        'tax_rate'                   => 'decimal:4',
        'amount'                     => 'decimal:2',
        'tax_amount'                 => 'decimal:2',
        'shown_unit_price'           => 'decimal:2',
        'shown_amount'               => 'decimal:2',
        'weight'                     => 'decimal:3',
        'shipped'                    => 'boolean',
        'position'                   => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }

    /** Phase F1: the price list its price came from, when it came from one. */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Phase G3a: a product that does not ship is a service, by its item - else a digital product. */
    public function isService(): bool
    {
        return $this->item?->type === Item::TYPE_SERVICE;
    }

    /** Phase G3a: how its item delivers it, as a digital product - by hand when the item says nothing. */
    public function deliveryMethod(): string
    {
        $method = $this->item?->delivery_method;

        return in_array($method, Item::DELIVERY_METHODS, true) ? $method : EcommerceOrderDelivery::MANUAL;
    }
}
