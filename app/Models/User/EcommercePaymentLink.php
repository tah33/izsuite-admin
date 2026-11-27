<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A secure payment link for an online order not fully paid (Doc 19 section
 * 19.23 - Phase F6a): the amount it asks, in the order's currency, the
 * store's payment methods it offers, until when it works, and to whom it was
 * e-mailed and by whom. Kept active, paid or revoked - revoked by hand,
 * replaced by a newer link, with its order cancelled, or with its total
 * changed by an edit; expired is an active link past its day (state()). Its
 * token is kept only hashed. It is never a sale of its own: the order's
 * invoice is.
 */
class EcommercePaymentLink extends Model
{
    public const ACTIVE = 'active';

    public const PAID = 'paid';

    public const REVOKED = 'revoked';

    /** Shown, not kept: an active link past its day. */
    public const EXPIRED = 'expired';

    /** Why a link was revoked: by hand, replaced by a newer one, its order cancelled, or its total changed by an edit (Phase F5). */
    public const BY_HAND = 'revoked';

    public const REPLACED = 'replaced';

    public const CANCELLED = 'cancelled';

    public const CHANGED = 'changed';

    /** Its methods' types - those paid from afar, before the order reaches the customer. */
    public const METHOD_TYPES = [EcommercePaymentMethod::BANK_TRANSFER, EcommercePaymentMethod::MOBILE_MONEY];

    protected $fillable = [
        'workspace_id',
        'ecommerce_order_id',
        'token_hash',
        'amount',
        'currency',
        // The store's methods it offers, [{id, name}] as they were when it was sent.
        'payment_methods',
        'expires_at',
        'status',
        'sent_to',
        'created_by',
        'revoked_at',
        'revoked_by',
        'revoked_why',
    ];

    protected $casts = [
        'ecommerce_order_id' => 'integer',
        'amount'             => 'decimal:2',
        'payment_methods'    => 'array',
        'expires_at'         => 'datetime',
        'created_by'         => 'integer',
        'revoked_at'         => 'datetime',
        'revoked_by'         => 'integer',
    ];

    protected $hidden = ['token_hash'];

    /** What it is now: active, paid or revoked as kept - an active one past its day expired. */
    public function state(): string
    {
        return $this->status === self::ACTIVE && $this->expires_at->isPast() ? self::EXPIRED : $this->status;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
