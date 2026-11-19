<?php

namespace App\Models\User;

use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A POS register (Doc 18 section 18.5 - Phase A2): a till at one location,
 * selling in one currency, with its price list (the full Sales app's), the
 * cash account its drawer's cash goes into, its receipt number prefix, the
 * payment methods it takes and its cashiers. Active, Inactive - it opens no
 * new sessions - or Maintenance; its history stays either way.
 */
class PosRegister extends Model
{
    use RecordsAuthors;

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const MAINTENANCE = 'maintenance';

    public const STATUSES = [self::ACTIVE, self::INACTIVE, self::MAINTENANCE];

    protected $fillable = [
        'name',
        'code',
        'location_id',
        'currency',
        'price_list_id',
        'cash_account_id',
        'receipt_prefix',
        'status',
    ];

    protected $casts = [
        'location_id'     => 'integer',
        'price_list_id'   => 'integer',
        'cash_account_id' => 'integer',
    ];

    // The column's default, so one just made reads as it was saved.
    protected $attributes = [
        'status' => self::ACTIVE,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /** The account its drawer's cash goes into: its cash methods' money, instead of theirs. */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'cash_account_id');
    }

    /** The payment methods it takes, in Settings → Point of Sale's order. */
    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(PosPaymentMethod::class, 'pos_register_payment_methods')->orderBy('position')->orderBy('pos_payment_methods.id');
    }

    /** Who may sell at it. */
    public function cashiers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pos_register_cashiers');
    }
}
