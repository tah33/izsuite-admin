<?php

namespace App\Models\User;

use App\Models\Concerns\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment against a recorded expense (Doc 10 sections 12 and 13), numbered
 * as its Expense Payment Voucher. `amount` left the account in the account's
 * currency; `applied_amount` is what it paid off the expense, in the
 * expense's; tax withheld from the payee settles the expense too, without
 * leaving the account. Completed until reversed - then it stays on record and
 * counts for nothing. contact_id is the payee contact, when the expense has
 * one. payment_date is left as the "2026-10-28" string the column holds. Its
 * attachments are files in the "attachments" collection.
 */
class ExpensePayment extends Model
{
    use HasFiles;

    public const COMPLETED = 'completed';

    public const REVERSED = 'reversed';

    protected $fillable = [
        'number',
        'expense_id',
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
        'expense_id'         => 'integer',
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

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** The payee, when one of the business's contacts. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    /** Its expense's location, which lists and reports filter and show it by. */
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
