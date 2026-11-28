<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product of a request for a quotation (Doc 19 Phase H4a): its listing,
 * variant and item - each gone to null if deleted - its title with its
 * options as it was asked for, and how many.
 */
class EcommerceQuoteRequestItem extends Model
{
    protected $fillable = [
        'catalog_product_id',
        'catalog_product_variant_id',
        'item_id',
        'title',
        'quantity',
        'position',
    ];

    protected $casts = [
        'catalog_product_id'         => 'integer',
        'catalog_product_variant_id' => 'integer',
        'item_id'                    => 'integer',
        'quantity'                   => 'decimal:3',
        'position'                   => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(EcommerceQuoteRequest::class, 'ecommerce_quote_request_id');
    }
}
