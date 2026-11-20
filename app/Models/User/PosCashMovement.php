<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cash movement of a POS session's drawer (Doc 18 section 18.26 - Phase
 * F1), numbered CM-00001, with why: cash put in or taken out, a safe drop, a
 * bank deposit, or a float adjustment either way. It moves money between the
 * drawer's cash account and another of the business's accounts, by a
 * transfer. Above Settings' limit it waits for a manager's approval -
 * nothing moved - until approved, or declined with why. Never deleted.
 *
 * Phase F2: a paid out is an expense paid from the drawer - to its payee, in
 * its category - recorded in Expenses and paid from the drawer's account.
 */
class PosCashMovement extends Model
{
    public const CASH_IN = 'cash_in';

    public const CASH_OUT = 'cash_out';

    public const SAFE_DROP = 'safe_drop';

    public const BANK_DEPOSIT = 'bank_deposit';

    public const FLOAT_ADJUSTMENT = 'float_adjustment';

    /** Phase F2: an expense paid from the drawer. */
    public const PAID_OUT = 'paid_out';

    public const TYPES = [self::CASH_IN, self::CASH_OUT, self::SAFE_DROP, self::BANK_DEPOSIT, self::FLOAT_ADJUSTMENT, self::PAID_OUT];

    /** Each kind as a sentence names it. */
    public const LABELS = [
        self::CASH_IN => 'cash in', self::CASH_OUT => 'cash out', self::SAFE_DROP => 'safe drop', self::BANK_DEPOSIT => 'bank deposit', self::FLOAT_ADJUSTMENT => 'float adjustment', self::PAID_OUT => 'paid out',
    ];

    public const IN = 'in';

    public const OUT = 'out';

    /** The way each kind goes - a float adjustment's is chosen. */
    public const DIRECTIONS = [self::CASH_IN => self::IN, self::CASH_OUT => self::OUT, self::SAFE_DROP => self::OUT, self::BANK_DEPOSIT => self::OUT, self::PAID_OUT => self::OUT];

    /** The kind of account each kind moves cash to: the safe, a cash account; the bank. Any, for the others. */
    public const ACCOUNT_TYPES = [self::SAFE_DROP => 'cash', self::BANK_DEPOSIT => 'bank'];

    public const COMPLETED = 'completed';

    public const PENDING = 'pending';

    public const DECLINED = 'declined';

    protected $fillable = [
        'number',
        'pos_session_id',
        'pos_register_id',
        'location_id',
        'type',
        'direction',
        'amount',
        'currency',
        'drawer_account_id',
        'payment_account_id',
        'account_transfer_id',
        // Phase F2: a paid out's payee and category, and its expense once it moved.
        'payee_name',
        'expense_category_id',
        'expense_id',
        'reason',
        'status',
        'created_by',
        'decided_by',
        'decided_at',
        'decline_reason',
    ];

    protected $casts = [
        'pos_session_id'      => 'integer',
        'pos_register_id'     => 'integer',
        'location_id'         => 'integer',
        'amount'              => 'decimal:2',
        'drawer_account_id'   => 'integer',
        'payment_account_id'  => 'integer',
        'account_transfer_id' => 'integer',
        'expense_category_id' => 'integer',
        'expense_id'          => 'integer',
        'created_by'          => 'integer',
        'decided_by'          => 'integer',
        'decided_at'          => 'datetime',
    ];

    protected $attributes = [
        'status' => self::COMPLETED,
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('A POS cash movement is never deleted.'));
    }

    /** "safe drop CM-00001 of USD 40.00", as the activity log says it. */
    public function describe(): string
    {
        return self::LABELS[$this->type]." {$this->number} of {$this->currency} {$this->amount}";
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    /** Phase G1: its register. */
    public function register(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'pos_register_id');
    }

    public function drawer(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'drawer_account_id');
    }

    /** The account it moves to or from. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(AccountTransfer::class, 'account_transfer_id');
    }

    /** Phase F2: a paid out's expense. */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
