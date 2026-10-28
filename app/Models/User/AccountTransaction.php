<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One money in or out of a payment account (Doc 05 sections 10 to 12): a row
 * of its history, written by the customer payment, refund or transfer it
 * records (`source`) and changed only through that.
 *
 * `amount` is always positive, in the account's currency; `direction` says
 * which way it went. Only a completed row counts towards the recorded
 * balance (section 10): a draft, reversed or void one stays in the history,
 * marked as such. transaction_date is the "2026-10-28" string the column
 * holds.
 */
class AccountTransaction extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    public const COMPLETED = 'completed';

    /** What moved the money, and which way: a payment comes in, a refund goes out. */
    public const TYPES = [
        'customer_payment' => self::IN,
        'customer_refund'  => self::OUT,
        'transfer_in'      => self::IN,
        'transfer_out'     => self::OUT,
    ];

    protected $fillable = [
        'payment_account_id',
        'transaction_date',
        'type',
        'direction',
        'amount',
        'base_amount',
        'status',
        'contact_id',
        'location_id',
        'sales_document_id',
        'source_type',
        'source_id',
        'reference',
        'created_by',
    ];

    protected $casts = [
        'payment_account_id' => 'integer',
        'amount'             => 'decimal:2',
        'base_amount'        => 'decimal:2',
        'contact_id'         => 'integer',
        'location_id'        => 'integer',
        'sales_document_id'  => 'integer',
        'source_id'          => 'integer',
    ];

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** The invoice a payment was for, or the credit note a refund came out of. */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    /** The payment, refund or transfer it records. */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
