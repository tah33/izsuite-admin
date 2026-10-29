<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cash drawer, bank account, mobile wallet, payment gateway or other account
 * one business takes or pays money with (Doc 05). Each uses one currency (Doc
 * 01 section 11): the business's base currency or one of its additional
 * currencies; it may belong to one location, or to the whole business.
 *
 * Its recorded balance is its opening balance - what it held on
 * opening_balance_date - plus the completed money in and less the completed
 * money out in its history (Doc 05 section 10). opening_balance_date is the
 * "2026-10-28" string the column holds.
 *
 * Its type decides which details it keeps (section 7): bank_name is the bank,
 * provider or description, account_number the account number, IBAN, wallet
 * number or reference; a bank adds its branch, SWIFT / BIC / routing code and
 * country, a wallet its merchant or till number, a gateway its merchant name
 * and settlement currency. allow_customer_payments and allow_supplier_payments
 * say what may be recorded in it; default_receiving and default_payment mark
 * the account each kind of payment starts with, one of each per currency and
 * location (section 8). show_on_documents prints its public details on
 * customer documents (section 9).
 */
class PaymentAccount extends Model
{
    public const TYPES = ['cash', 'bank', 'mobile_money', 'payment_gateway', 'other'];

    /** What each kind of default is for, and the flag an account needs to be it. */
    public const DEFAULTS = ['receiving' => 'allow_customer_payments', 'payment' => 'allow_supplier_payments'];

    protected $fillable = [
        'workspace_id',
        'type',
        'name',
        'currency',
        'location_id',
        'bank_name',
        'holder_name',
        'account_number',
        'branch',
        'swift_code',
        'bank_country',
        'merchant_name',
        'merchant_number',
        'settlement_currency',
        'opening_balance',
        'opening_balance_date',
        'payment_instructions',
        'show_on_documents',
        'allow_customer_payments',
        'allow_supplier_payments',
        'default_receiving',
        'default_payment',
        'notes',
        'is_active',
    ];

    /** The column defaults, so an account just made reads the same as one fetched. */
    protected $attributes = [
        'opening_balance'         => 0,
        'show_on_documents'       => false,
        'allow_customer_payments' => true,
        'allow_supplier_payments' => true,
        'default_receiving'       => false,
        'default_payment'         => false,
        'is_active'               => true,
    ];

    protected $casts = [
        'location_id'             => 'integer',
        'opening_balance'         => 'decimal:2',
        'show_on_documents'       => 'boolean',
        'allow_customer_payments' => 'boolean',
        'allow_supplier_payments' => 'boolean',
        'default_receiving'       => 'boolean',
        'default_payment'         => 'boolean',
        'is_active'               => 'boolean',
    ];

    /**
     * The Payment Accounts sidebar item shows once a business has one (Doc 01
     * section 6.2), so the first one made and the last one deleted each change
     * the sidebar: the business's nav_version goes up, and it is rebuilt.
     */
    protected static function booted(): void
    {
        static::created(function (self $account) {
            if ($account->workspace->paymentAccounts()->count() === 1) {
                $account->workspace->increment('nav_version');
            }
        });

        static::deleted(function (self $account) {
            if (! $account->workspace->paymentAccounts()->exists()) {
                $account->workspace->increment('nav_version');
            }
        });
    }

    // ── Relationships ──

    /** The business it belongs to. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The location it belongs to - none for the whole business. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Its history: each money in or out, whatever its status. */
    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }
}
