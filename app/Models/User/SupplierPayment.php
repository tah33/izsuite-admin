<?php

namespace App\Models\User;

use App\Models\Concerns\AtLocation;
use App\Models\Concerns\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money paid to a supplier against a recorded purchase (Doc 09 sections 12
 * and 13), numbered as its Supplier Payment Voucher (SPV-00001) when it is
 * recorded.
 *
 * Its `amount` is what left the payment account, in the account's currency;
 * `applied_amount` is what it took off the purchase, in the purchase's
 * currency, at `exchange_rate` (account currency per unit of the purchase's).
 * Withholding tax kept back from the supplier also comes off the purchase,
 * but never leaves the account.
 *
 * A completed one is never edited or deleted: it is reversed, which leaves it
 * on record and takes it out of every balance. Its attachments are files in
 * the "attachments" collection. payment_date is the "2026-10-28" string the
 * column holds, like a purchase's dates.
 */
class SupplierPayment extends Model
{
    use AtLocation;
    use HasFiles;

    public const COMPLETED = 'completed';

    public const REVERSED = 'reversed';

    protected $fillable = [
        'number',
        'purchase_id',
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
        'purchase_id'        => 'integer',
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

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** The supplier. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    /** Its purchase's location, which lists and reports filter and show it by. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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
