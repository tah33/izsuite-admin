<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A storefront customer's account (Doc 19 section 19.16 - Phase E1): the
 * business's, one per e-mail (kept in small letters), signing its customer
 * in on each of the business's storefronts. Never a user of the business,
 * nor counted as one: its sign-ins are tokens of its own
 * (StorefrontCustomerToken), which no business route reads.
 *
 * Its e-mail is verified by a code sent to it - or by a password reset's.
 * Active while it is used; closed by its customer - kept, with its orders,
 * and opened again by signing up with its e-mail. Phase E2 links it to its
 * contact.
 */
class StorefrontCustomer extends Model
{
    public const ACTIVE = 'active';

    public const CLOSED = 'closed';

    protected $fillable = [
        'contact_id',
        'storefront_id',
        'name',
        'email',
        'phone',
        // Phase E3: one of the store's languages, as they choose.
        'language',
        'password',
        'email_verified_at',
        'status',
        'closed_at',
        'last_signed_in_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'workspace_id'      => 'integer',
        'contact_id'        => 'integer',
        'storefront_id'     => 'integer',
        'password'          => 'hashed',
        'email_verified_at' => 'datetime',
        'closed_at'         => 'datetime',
        'last_signed_in_at' => 'datetime',
    ];

    // The column's default, so one just made reads as it was saved.
    protected $attributes = [
        'status' => self::ACTIVE,
    ];

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** The storefront it signed up on: its e-mails' store. */
    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(StorefrontCustomerToken::class);
    }

    public function codes(): HasMany
    {
        return $this->hasMany(StorefrontCustomerCode::class);
    }

    /** Phase E2: its saved addresses. */
    public function addresses(): HasMany
    {
        return $this->hasMany(StorefrontCustomerAddress::class);
    }

    /** Phase E2: its orders - placed signed in, or its e-mail's guest orders once verified. */
    public function orders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class);
    }
}
