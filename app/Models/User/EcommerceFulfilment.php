<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shipment of an online order's products, or its readiness for pickup (Doc
 * 19 section 19.27 - Phase G1): numbered 1, 2… within its order; from its
 * location; on a day, with its carrier, tracking number and link, and the
 * business's own notes. A shipment is Shipped, then Delivered; readiness is
 * Ready, then Collected; either may be Cancelled before that, with why, its
 * quantities to fulfil again. No stock moves, and it is never a sale.
 */
class EcommerceFulfilment extends Model
{
    public const SHIPMENT = 'shipment';

    public const PICKUP = 'pickup';

    public const SHIPPED = 'shipped';

    public const DELIVERED = 'delivered';

    public const READY = 'ready';

    public const COLLECTED = 'collected';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'workspace_id',
        'ecommerce_order_id',
        'sequence',
        'kind',
        'status',
        'location_id',
        'fulfilled_on',
        'carrier',
        'tracking_number',
        'tracking_url',
        'notes',
        'completed_on',
        'cancel_reason',
        'created_by',
    ];

    protected $casts = [
        'ecommerce_order_id' => 'integer',
        'sequence'           => 'integer',
        'location_id'        => 'integer',
        'fulfilled_on'       => 'date:Y-m-d',
        'completed_on'       => 'date:Y-m-d',
        'created_by'         => 'integer',
    ];

    /** Delivered or collected: it reached its customer. */
    public function isComplete(): bool
    {
        return in_array($this->status, [self::DELIVERED, self::COLLECTED], true);
    }

    /** Shipped or ready: on its way, or waiting to be collected. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::SHIPPED, self::READY], true);
    }

    /** What its customer and timeline call it: "shipment 2", or "pickup". */
    public function label(): string
    {
        return $this->kind === self::PICKUP ? 'pickup' : "shipment {$this->sequence}";
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EcommerceFulfilmentLine::class)->orderBy('id');
    }
}
