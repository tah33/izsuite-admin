<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment of a POS order (Doc 18 section 18.16 - Phase C2): its method,
 * named as it was then, the account its money goes into, the customer
 * payment it is, what it paid, what was handed over, the change and its
 * reference.
 *
 * By a method that waits to be confirmed (Phase D2) it is pending - no
 * customer payment yet, nothing in its account - until someone who may
 * confirms it, and it becomes one; or declines it, saying why, and the
 * invoice still owes it.
 */
class PosOrderPayment extends Model
{
    public const COMPLETED = 'completed';

    public const PENDING = 'pending';

    public const DECLINED = 'declined';

    protected $fillable = [
        'pos_payment_method_id',
        'method_name',
        'method_type',
        'payment_account_id',
        'sales_payment_id',
        'amount',
        'tendered',
        'change_given',
        'reference',
        // Phase D2: pending until confirmed - or declined, and why.
        'status',
        'closed_by',
        'closed_at',
        'decline_reason',
    ];

    protected $casts = [
        'pos_payment_method_id' => 'integer',
        'payment_account_id'    => 'integer',
        'sales_payment_id'      => 'integer',
        'amount'                => 'decimal:2',
        'tendered'              => 'decimal:2',
        'change_given'          => 'decimal:2',
        'closed_by'             => 'integer',
        'closed_at'             => 'datetime',
    ];

    protected $attributes = [
        'status' => self::COMPLETED,
    ];

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PosPaymentMethod::class, 'pos_payment_method_id');
    }

    public function salesPayment(): BelongsTo
    {
        return $this->belongsTo(SalesPayment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }
}
