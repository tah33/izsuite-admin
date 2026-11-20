<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A POS order (Doc 18 sections 18.9, 18.10 and 18.18 - Phase C2): a sale at
 * a register, in a session, by its cashier, to its customer, in the
 * register's currency. Completed, it is its invoice - numbered by the
 * register's receipt run, which is the order's number too - paid by its
 * payments. It keeps what was handed over and the change. A completed order
 * is never deleted: Phase E's returns and refunds correct one.
 */
class PosOrder extends Model
{
    public const COMPLETED = 'completed';

    protected $fillable = [
        'pos_session_id',
        'pos_register_id',
        'location_id',
        'cashier_id',
        'contact_id',
        'sales_document_id',
        'number',
        'status',
        'currency',
        'exchange_rate',
        'grand_total',
        'tendered',
        'change_given',
        'notes',
        'completed_at',
        // Phase C3: why its discounts went beyond the limit, who allowed it, and its cash rounding.
        'discount_reason',
        'discount_approved_by',
        'cash_rounding',
        // Phase C4: how many copies of its receipt were made - after the first, each a reprint - and the return policy and footer it ends with.
        'receipt_prints',
        'receipt_texts',
        // Phase D3a: what was left on the customer's account, and why it went over their credit limit.
        'on_account',
        'credit_reason',
        // Phase D3b: how much of the customer's own credit paid it.
        'credit_applied',
        // Phase D4: when its invoice was given the business's invoice number.
        'invoiced_at',
        // Phase E3: the exchange's return that paid it, how much, and the difference paid back.
        'exchange_return_id',
        'exchange_credit',
        'exchange_refunded',
    ];

    protected $casts = [
        'pos_session_id'    => 'integer',
        'pos_register_id'   => 'integer',
        'location_id'       => 'integer',
        'cashier_id'        => 'integer',
        'contact_id'        => 'integer',
        'sales_document_id' => 'integer',
        'exchange_rate'     => 'decimal:8',
        'grand_total'       => 'decimal:2',
        'tendered'          => 'decimal:2',
        'change_given'      => 'decimal:2',
        'completed_at'      => 'datetime',
        'discount_approved_by' => 'integer',
        'cash_rounding'     => 'decimal:2',
        'receipt_prints'    => 'integer',
        'receipt_texts'     => 'array',
        'on_account'        => 'decimal:2',
        'credit_applied'    => 'decimal:2',
        'invoiced_at'       => 'datetime',
        'exchange_return_id' => 'integer',
        'exchange_credit'   => 'decimal:2',
        'exchange_refunded' => 'decimal:2',
    ];

    protected $attributes = [
        'status' => self::COMPLETED,
    ];

    /** Said as it is sold, never kept (Phase D3a): the customer is over their credit limit, which warns. */
    public ?string $creditWarning = null;

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('A POS order is never deleted.'));
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
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

    /** The invoice it is. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    /** Phase G1: the returns made from it. */
    public function returns(): HasMany
    {
        return $this->hasMany(PosReturn::class);
    }

    /** Phase E3: the exchange's return that paid it. */
    public function exchangeReturn(): BelongsTo
    {
        return $this->belongsTo(PosReturn::class, 'exchange_return_id');
    }

    /** How it was paid, in the order the methods were taken. */
    public function payments(): HasMany
    {
        return $this->hasMany(PosOrderPayment::class)->orderBy('id');
    }

    /** Where each of its invoice's lines' prices came from. */
    public function lines(): HasMany
    {
        return $this->hasMany(PosOrderLine::class)->orderBy('id');
    }
}
