<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a business's POS payment methods (Doc 18 section 18.15 - Phase A1),
 * in Settings → Point of Sale's order: its name, type, the payment account
 * it takes money into - its currency the account's - whether the cashier
 * must enter a reference, whether it may give change (cash only), and
 * whether it is on. Without an active account it cannot be used.
 */
class PosPaymentMethod extends Model
{
    public const CASH = 'cash';

    public const TYPES = [self::CASH, 'card', 'mobile_money', 'bank_transfer', 'other'];

    protected $fillable = [
        'name',
        'type',
        'payment_account_id',
        'needs_reference',
        'gives_change',
        'is_active',
        'position',
    ];

    protected $casts = [
        'payment_account_id' => 'integer',
        'needs_reference'    => 'boolean',
        'gives_change'       => 'boolean',
        'is_active'          => 'boolean',
        'position'           => 'integer',
    ];

    // The columns' defaults, so one just made reads as it was saved.
    protected $attributes = [
        'needs_reference' => false,
        'gives_change'    => false,
        'is_active'       => true,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }
}
