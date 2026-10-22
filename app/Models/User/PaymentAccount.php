<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cash drawer, bank account, mobile wallet or card one business takes or
 * pays money with. Each uses one currency (Doc 01 section 11): the business's
 * base currency or one of its additional currencies.
 */
class PaymentAccount extends Model
{
    public const TYPES = ['cash', 'bank', 'mobile_money', 'card', 'other'];

    protected $fillable = [
        'workspace_id',
        'type',
        'name',
        'currency',
        'bank_name',
        'account_number',
        'opening_balance',
        'notes',
        'is_active',
    ];

    /** The column defaults, so an account just made reads the same as one fetched. */
    protected $attributes = [
        'opening_balance' => 0,
        'is_active'       => true,
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active'       => 'boolean',
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
}
