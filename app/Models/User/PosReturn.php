<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A POS return (Doc 18 sections 18.23 and 18.24 - Phase E1): items brought
 * back from a sale - its receipt - numbered RT-00001, at the register of the
 * cashier's session, each with its condition, and why. It is its credit note
 * of the sale's invoice, and its refund: paid out in cash or by the sale's
 * own method, or left as the customer's credit. Never deleted.
 *
 * Phase E2: paid back by another of the register's methods, with why; by a
 * method that waits to be confirmed, its refund is pending - what it will
 * pay out of which account - until confirmed, or declined, with why.
 */
class PosReturn extends Model
{
    public const CASH = 'cash';

    public const ORIGINAL = 'original';

    public const CREDIT = 'credit';

    /** Phase E2: another of the register's methods. */
    public const METHOD = 'method';

    /** Phase E3: its credit pays the sale of the replacement. */
    public const EXCHANGE = 'exchange';

    public const REFUNDS = [self::CASH, self::ORIGINAL, self::CREDIT, self::METHOD, self::EXCHANGE];

    public const COMPLETED = 'completed';

    public const PENDING = 'pending';

    public const DECLINED = 'declined';

    public const CONDITIONS = ['resellable', 'damaged', 'quarantined', 'not_returned'];

    protected $fillable = [
        'number',
        'pos_order_id',
        'pos_session_id',
        'pos_register_id',
        'location_id',
        'cashier_id',
        'contact_id',
        'credit_note_id',
        'reason',
        'notes',
        'currency',
        'total',
        'refund_method',
        'refund_method_name',
        'refund_method_type',
        'refund_payment_id',
        'refunded',
        // Phase E2: a refund by another method, why; one waiting to be confirmed, what and from where - or declined, why.
        'refund_status',
        'refund_reason',
        // Phase I2: the manager who approved paying it back by another method at the register.
        'refund_approved_by',
        'refund_account_id',
        'refund_due',
        'refund_closed_by',
        'refund_closed_at',
        'refund_decline_reason',
        // Phase E3: the sale of the replacement it paid.
        'exchange_order_id',
    ];

    protected $casts = [
        'pos_order_id'      => 'integer',
        'pos_session_id'    => 'integer',
        'pos_register_id'   => 'integer',
        'location_id'       => 'integer',
        'cashier_id'        => 'integer',
        'contact_id'        => 'integer',
        'credit_note_id'    => 'integer',
        'refund_payment_id' => 'integer',
        'total'             => 'decimal:2',
        'refunded'          => 'decimal:2',
        'refund_account_id' => 'integer',
        'refund_due'        => 'decimal:2',
        'refund_closed_by'  => 'integer',
        'refund_closed_at'  => 'datetime',
        'exchange_order_id' => 'integer',
    ];

    protected $attributes = [
        'refund_status' => self::COMPLETED,
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('A POS return is never deleted.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    /** Phase G1: the session it was made in. */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    /** Phase E3: the sale of its replacement, made as an exchange. */
    public function exchangeOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'exchange_order_id');
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'credit_note_id');
    }

    /** Doc 18 Phase G3: where it was made. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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

    public function lines(): HasMany
    {
        return $this->hasMany(PosReturnLine::class)->orderBy('id');
    }
}
