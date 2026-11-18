<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money a customer paid ahead (Doc 16 section 16.15), recorded from the
 * customer or a sales order, or the extra of a payment over its invoice kept
 * once the user confirmed it (section 16.16). It is not a sale: it stays
 * unapplied until allocations apply it to the customer's issued invoices in
 * its currency.
 *
 * `amount` is the advance, in its `currency`; `account_amount` is what reached
 * the payment account, in the account's currency, at `exchange_rate`. Like a
 * payment it is reversed, never edited or deleted - and only once nothing of
 * it is applied.
 */
class CustomerAdvance extends Model
{
    public const COMPLETED = 'completed';

    public const REVERSED = 'reversed';

    protected $fillable = [
        'number',
        'contact_id',
        'sales_document_id',
        'sales_payment_id',
        'payment_account_id',
        'location_id',
        'payment_date',
        'method',
        'reference',
        'notes',
        'currency',
        'amount',
        'account_amount',
        'exchange_rate',
        'base_amount',
        'allocated_amount',
        'balance',
        'status',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'created_by',
    ];

    protected $attributes = [
        'status'           => self::COMPLETED,
        'allocated_amount' => 0,
    ];

    protected $casts = [
        'contact_id'         => 'integer',
        'sales_document_id'  => 'integer',
        'sales_payment_id'   => 'integer',
        'payment_account_id' => 'integer',
        'location_id'        => 'integer',
        'amount'             => 'decimal:2',
        'account_amount'     => 'decimal:2',
        'exchange_rate'      => 'decimal:8',
        'base_amount'        => 'decimal:2',
        'allocated_amount'   => 'decimal:2',
        'balance'            => 'decimal:2',
        'reversed_at'        => 'datetime',
    ];

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** The sales order it was paid for, or the invoice it was paid over. */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    /** The payment whose extra it kept. */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SalesPayment::class, 'sales_payment_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Where it was applied, the removed ones too, newest first. */
    public function allocations(): HasMany
    {
        return $this->hasMany(SalesAllocation::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
