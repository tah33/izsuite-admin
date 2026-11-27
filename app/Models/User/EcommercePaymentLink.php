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
 * invoice is. From Phase F6b its customer reports on its page the way they
 * paid and their reference: an active link so reported reads as reported,
 * past its day or not, until the business records the payment (it is then
 * paid) or marks it not received (the report is cleared).
 */
class EcommercePaymentLink extends Model
{
    public const ACTIVE = 'active';

    public const PAID = 'paid';

    public const REVOKED = 'revoked';

    /** Shown, not kept: an active link past its day. */
    public const EXPIRED = 'expired';

    /** Shown, not kept: an active link its customer says they paid through (Phase F6b). */
    public const REPORTED = 'reported';

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
        // Phase F6b: its customer's report - when, the way they paid ({id, name, type, payment_account_id}) and their reference.
        'reported_at',
        'reported_method',
        'reported_reference',
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
        'reported_at'        => 'datetime',
        'reported_method'    => 'array',
        'created_by'         => 'integer',
        'revoked_at'         => 'datetime',
        'revoked_by'         => 'integer',
    ];

    protected $hidden = ['token_hash'];

    /** What it is now: paid or revoked as kept; an active one reported by its customer, past its day, or active. */
    public function state(): string
    {
        return match (true) {
            $this->status !== self::ACTIVE => $this->status,
            $this->reported_at !== null    => self::REPORTED,
            $this->expires_at->isPast()    => self::EXPIRED,
            default                        => self::ACTIVE,
        };
    }

    /** What it asks of its order owing $owed: its amount, never more - paid in part since. */
    public function askedOf(string $owed): string
    {
        return bccomp($owed, (string) $this->amount, 2) < 0 && bccomp($owed, '0', 2) > 0 ? $owed : (string) $this->amount;
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
