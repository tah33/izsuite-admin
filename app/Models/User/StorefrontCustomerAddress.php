<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An address a storefront customer keeps on their account (Doc 19 Phase E2),
 * as checkout takes one: added on the account's page, or kept from an order
 * placed signed in. One may be the default for billing, and one - the same
 * or another - for delivery. Up to MAX; closing the account deletes them.
 */
class StorefrontCustomerAddress extends Model
{
    public const MAX = 20;

    /** What an address is made of, as checkout and an order keep it. */
    public const FIELDS = ['line1', 'line2', 'city', 'region', 'postal_code', 'country'];

    protected $fillable = [
        'line1',
        'line2',
        'city',
        'region',
        'postal_code',
        'country',
        'is_default_billing',
        'is_default_shipping',
    ];

    protected $casts = [
        'is_default_billing'  => 'boolean',
        'is_default_shipping' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(StorefrontCustomer::class, 'storefront_customer_id');
    }
}
