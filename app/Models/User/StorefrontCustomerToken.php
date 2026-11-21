<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A storefront customer's sign-in (Doc 19 Phase E1): the hash of the token
 * their browser keeps, when it was last used, and when it expires - kept on
 * while it is used.
 */
class StorefrontCustomerToken extends Model
{
    protected $fillable = [
        'token_hash',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(StorefrontCustomer::class, 'storefront_customer_id');
    }
}
