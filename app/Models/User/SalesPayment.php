<?php

namespace App\Models\User;

use App\Models\Concerns\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Money a customer paid against an issued invoice (kind "payment", Doc 08
 * sections 13 and 14) or money paid back to them out of a credit note (kind
 * "refund", section 16).
 *
 * A payment is numbered as its receipt (RCT-00001) when it is recorded. Its
 * `amount` is what reached the payment account, in the account's currency;
 * `applied_amount` is what it took off the invoice, in the invoice's
 * currency, at `exchange_rate` (account currency per unit of the invoice's).
 * Withholding tax the customer kept back also comes off the invoice, but never
 * reaches the account. A refund works the other way round: `amount` left the
 * account, `applied_amount` came off the credit note.
 *
 * A completed one is never edited or deleted: it is reversed, which leaves it
 * on record and takes it out of every balance. Its attachments are files in
 * the "attachments" collection. payment_date is the "2026-10-28" string the
 * column holds, like a document's dates.
 */
class SalesPayment extends Model
{
    use HasFiles;

    public const PAYMENT = 'payment';

    public const REFUND = 'refund';

    public const COMPLETED = 'completed';

    public const REVERSED = 'reversed';

    /** How the money came or went (Doc 08 section 13). */
    public const METHODS = ['cash', 'bank_transfer', 'card', 'mobile_money', 'cheque', 'other'];

    protected $fillable = [
        'kind',
        'number',
        'sales_document_id',
        'contact_id',
        'payment_account_id',
        'location_id',
        'payment_date',
        'method',
        'reference',
        'notes',
        'amount',
        'applied_amount',
        'exchange_rate',
        'base_amount',
        'withholding_tax_id',
        'withholding_name',
        'withholding_code',
        'withholding_rate',
        'withholding_amount',
        'balance_after',
        'template_snapshot',
        'status',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'created_by',
    ];

    protected $attributes = [
        'status'             => self::COMPLETED,
        'withholding_amount' => 0,
    ];

    protected $casts = [
        'sales_document_id'  => 'integer',
        'contact_id'         => 'integer',
        'payment_account_id' => 'integer',
        'location_id'        => 'integer',
        'amount'             => 'decimal:2',
        'applied_amount'     => 'decimal:2',
        'exchange_rate'      => 'decimal:8',
        'base_amount'        => 'decimal:2',
        'withholding_tax_id' => 'integer',
        'withholding_rate'   => 'decimal:4',
        'withholding_amount' => 'decimal:2',
        'balance_after'      => 'decimal:2',
        'template_snapshot'  => 'array',
        'reversed_at'        => 'datetime',
    ];

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function isRefund(): bool
    {
        return $this->kind === self::REFUND;
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The invoice paid, or the credit note refunded. */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    /** The customer. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    /** Its document's location, which reports filter and show it by. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function withholdingTax(): BelongsTo
    {
        return $this->belongsTo(WithholdingTax::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /** Its receipt emailed, newest first. */
    public function emails(): MorphMany
    {
        return $this->morphMany(DocumentEmail::class, 'emailable')->latest('id');
    }
}
