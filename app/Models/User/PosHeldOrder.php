<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An order held at a POS register (Doc 18 section 18.22 - Phase D1): an
 * unfinished sale put aside - its customer, lines with their notes, prices
 * typed over and discounts, the order's discount and note, as the cart had
 * them - with each line's price then and the total. Held, it waits to be
 * resumed at its register - priced again then - or cancelled, until it
 * expires. It is no sale: nothing of it reaches Sales, stock or the payment
 * accounts.
 */
class PosHeldOrder extends Model
{
    public const HELD = 'held';

    public const RESUMED = 'resumed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    protected $fillable = [
        'number',
        'pos_register_id',
        'pos_session_id',
        'location_id',
        'cashier_id',
        'contact_id',
        'cart',
        'prices',
        'currency',
        'grand_total',
        'status',
        'expires_at',
        'closed_by',
        'closed_at',
        'cancel_reason',
    ];

    protected $casts = [
        'pos_register_id' => 'integer',
        'pos_session_id'  => 'integer',
        'location_id'     => 'integer',
        'cashier_id'      => 'integer',
        'contact_id'      => 'integer',
        'cart'            => 'array',
        'prices'          => 'array',
        'grand_total'     => 'decimal:2',
        'expires_at'      => 'datetime',
        'closed_by'       => 'integer',
        'closed_at'       => 'datetime',
    ];

    protected $attributes = [
        'status' => self::HELD,
    ];

    /** Still waiting: held, and not past its time. */
    public function isWaiting(): bool
    {
        return $this->status === self::HELD && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'pos_register_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
