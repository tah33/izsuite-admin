<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way a storefront's customers pay without a payment gateway (Doc 19
 * section 19.22 - Phase D2): bank transfer, mobile money or a wallet, cash on
 * delivery, or paying on pickup. Each takes money into one payment account of
 * the business - for orders in that account's currency - and shows its
 * instructions to the customer.
 *
 * Its customers may give their payment's reference (a transaction ID) at
 * checkout - not asked, optional or required. It is offered in the countries
 * it names (none: every one), for orders within its amounts, shipped, picked
 * up or either: cash on delivery for shipped orders, paying on pickup for
 * picked-up ones. Cards and gateways wait for the payment integrations.
 */
class EcommercePaymentMethod extends Model
{
    public const BANK_TRANSFER = 'bank_transfer';

    public const MOBILE_MONEY = 'mobile_money';

    public const CASH_ON_DELIVERY = 'cash_on_delivery';

    public const PAY_ON_PICKUP = 'pay_on_pickup';

    public const TYPES = [self::BANK_TRANSFER, self::MOBILE_MONEY, self::CASH_ON_DELIVERY, self::PAY_ON_PICKUP];

    /** Whether the customer gives their payment's reference. */
    public const REFERENCES = ['none', 'optional', 'required'];

    /** For which orders: shipped, picked up, or either. */
    public const ANY = 'any';

    public const SHIPPED = 'shipped';

    public const PICKUP = 'pickup';

    public const DELIVERIES = [self::ANY, self::SHIPPED, self::PICKUP];

    protected $fillable = [
        'name',
        'type',
        'payment_account_id',
        'instructions',
        'reference',
        'countries',
        'min_order',
        'max_order',
        'delivery',
        'is_active',
        'position',
    ];

    protected $casts = [
        'payment_account_id' => 'integer',
        'countries'          => 'array',
        'min_order'          => 'decimal:2',
        'max_order'          => 'decimal:2',
        'is_active'          => 'boolean',
        'position'           => 'integer',
    ];

    // The columns' defaults, so one just made reads as it was saved.
    protected $attributes = [
        'reference' => 'none',
        'delivery'  => self::ANY,
        'is_active' => true,
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
