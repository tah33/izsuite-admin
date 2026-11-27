<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A discount an online order took at checkout (Doc 19 section 19.31 - Phases
 * H1 and H2a), kept as it was then: its name, its code (none when it was
 * automatic), what it takes off, and how much it took - as the business keeps
 * prices, in the order's currency. Together an order's are its discount
 * (Phase F5b's). The discount itself may change or go since; this stays.
 */
class EcommerceOrderPromotion extends Model
{
    protected $fillable = [
        'ecommerce_discount_id',
        'name',
        'code',
        'kind',
        'value',
        'off',
    ];

    protected $casts = [
        'ecommerce_discount_id' => 'integer',
        'value'                 => 'decimal:4',
        'off'                   => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(EcommerceDiscount::class, 'ecommerce_discount_id');
    }
}
