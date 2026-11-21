<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

/**
 * A code e-mailed to a storefront customer (Doc 19 Phase E1): to verify
 * their e-mail, or to reset their password. Only its hash is kept, with how
 * often it was tried, when it expires and when it was used.
 */
class StorefrontCustomerCode extends Model
{
    public const VERIFY = 'verify';

    public const RESET = 'reset';

    /** Tries before a code is spent. */
    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'attempts'   => 'integer',
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];
}
