<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a business's withholding tax rates (Doc 11 section 12): "WHT 5%", the
 * part of a payment the payer keeps back and pays to the tax office instead.
 * Its code is upper case and used once in the business. It is in effect from
 * effective_from to effective_to - open-ended when that is empty.
 *
 * It applies to customer payments, supplier payments, expense payments or
 * several of them. Withheld tax settles what is owed without reaching the
 * payment account (section 12: it does not move an account twice).
 */
class WithholdingTax extends Model
{
    public const CUSTOMER_PAYMENTS = 'customer_payments';

    public const SUPPLIER_PAYMENTS = 'supplier_payments';

    public const EXPENSE_PAYMENTS = 'expense_payments';

    /** What a rate can apply to - and the column that says it does: for_customer_payments... */
    public const SIDES = [self::CUSTOMER_PAYMENTS, self::SUPPLIER_PAYMENTS, self::EXPENSE_PAYMENTS];

    protected $fillable = [
        'name',
        'code',
        'rate',
        'for_customer_payments',
        'for_supplier_payments',
        'for_expense_payments',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $attributes = [
        'for_customer_payments' => false,
        'for_supplier_payments' => false,
        'for_expense_payments'  => false,
        'is_active'             => true,
    ];

    /**
     * effective_from and effective_to are left as the "2026-10-28" strings the
     * columns hold, like a tax rate's: a date cast would store them with a time
     * on SQLite.
     */
    protected $casts = [
        'rate'                  => 'decimal:4',
        'for_customer_payments' => 'boolean',
        'for_supplier_payments' => 'boolean',
        'for_expense_payments'  => 'boolean',
        'is_active'             => 'boolean',
    ];

    /** What it applies to: ['customer_payments']. */
    public function appliesTo(): array
    {
        return array_values(array_filter(self::SIDES, fn (string $side) => $this->{"for_{$side}"}));
    }

    /** Whether it can be withheld from a $side payment dated $date. */
    public function usableOn(string $side, string $date): bool
    {
        return $this->is_active && $this->{"for_{$side}"}
            && $this->effective_from <= $date && (! $this->effective_to || $this->effective_to >= $date);
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
