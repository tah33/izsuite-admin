<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money moved from one of a business's payment accounts to another (Doc 05
 * section 13): cash banked, a wallet emptied into the bank. It is neither a
 * sale nor an expense.
 *
 * Between accounts in different currencies, `amount` is what left the From
 * account and `amount_received` what reached the To account; the exchange
 * rate is the one that made it. A draft can be changed or deleted; a
 * completed transfer is voided instead, which takes it out of both balances.
 * transfer_date is the "2026-10-28" string the column holds.
 */
class AccountTransfer extends Model
{
    public const DRAFT = 'draft';

    public const COMPLETED = 'completed';

    public const VOID = 'void';

    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'transfer_date',
        'amount',
        'amount_received',
        'exchange_rate',
        'base_amount',
        'reference',
        'notes',
        'status',
        'void_reason',
        'voided_at',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => self::DRAFT,
    ];

    protected $casts = [
        'from_account_id' => 'integer',
        'to_account_id'   => 'integer',
        'amount'          => 'decimal:2',
        'amount_received' => 'decimal:2',
        'exchange_rate'   => 'decimal:8',
        'base_amount'     => 'decimal:2',
        'voided_at'       => 'datetime',
    ];

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'to_account_id');
    }
}
