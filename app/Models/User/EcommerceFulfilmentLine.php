<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much of one of an online order's products a shipment or pickup holds (Doc 19 Phase G1). */
class EcommerceFulfilmentLine extends Model
{
    protected $fillable = [
        'ecommerce_fulfilment_id',
        'ecommerce_order_line_id',
        'quantity',
    ];

    protected $casts = [
        'ecommerce_fulfilment_id' => 'integer',
        'ecommerce_order_line_id' => 'integer',
        'quantity'                => 'decimal:3',
    ];

    public function fulfilment(): BelongsTo
    {
        return $this->belongsTo(EcommerceFulfilment::class, 'ecommerce_fulfilment_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrderLine::class, 'ecommerce_order_line_id');
    }
}
