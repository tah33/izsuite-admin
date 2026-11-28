<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A visitor's cart on a storefront (Doc 19 section 19.14 - Phase D3), known
 * by a token their browser keeps - only its hash is kept here. Active while
 * it is used; Expired after Settings → eCommerce's days without activity;
 * Converted once its order is placed (Phase D4).
 *
 * From Phase H3a (19.32) it knows its customer once checkout does - the
 * account signed in, or the name and e-mail typed - and whether they agreed
 * to marketing by e-mail. Such a cart unused for Settings → eCommerce's hours
 * is Abandoned, and Active again once used; when it was last found abandoned
 * stays with it. An expired cart's customer is cleared.
 *
 * From Phase H3b an abandoned cart whose customer agreed to marketing may be
 * e-mailed once, by hand, with a link back to it (only its token's hash is
 * kept). Converted after that e-mail, it is Recovered.
 *
 * It is not an order or a sale, and reserves nothing. Its currency and
 * totals are as it was last priced, the way the store shows prices - for
 * the business's lists of carts: what it shows its visitor is always priced
 * again.
 */
class EcommerceCart extends Model
{
    public const ACTIVE = 'active';

    public const CONVERTED = 'converted';

    public const ABANDONED = 'abandoned';

    public const EXPIRED = 'expired';

    public const STATUSES = [self::ACTIVE, self::ABANDONED, self::CONVERTED, self::EXPIRED];

    /** The statuses of a cart its visitor may still use: an abandoned one is theirs still. */
    public const OPEN = [self::ACTIVE, self::ABANDONED];

    /** How many lines a cart takes, and how many of one product a line. */
    public const MAX_LINES = 50;

    public const MAX_QUANTITY = 999;

    protected $fillable = [
        'storefront_id',
        'token_hash',
        'status',
        'currency',
        'subtotal',
        'tax_total',
        'grand_total',
        'last_activity_at',
        'expires_at',
        // Phase D4: when its order was placed.
        'converted_at',
        // Phase H3a: its customer, as checkout learns them, and when it was last found abandoned.
        'storefront_customer_id',
        'customer_name',
        'customer_email',
        'marketing',
        'abandoned_at',
        // Phase H3b: its recovery e-mail - its link's token's hash, when and by whom it was sent - and when its link was opened.
        'recovery_token_hash',
        'recovery_sent_at',
        'recovery_sent_by',
        'recovery_opened_at',
    ];

    protected $hidden = ['token_hash', 'recovery_token_hash'];

    protected $casts = [
        'storefront_id'          => 'integer',
        'storefront_customer_id' => 'integer',
        'subtotal'               => 'decimal:2',
        'tax_total'              => 'decimal:2',
        'grand_total'            => 'decimal:2',
        'marketing'              => 'boolean',
        'last_activity_at'       => 'datetime',
        'abandoned_at'           => 'datetime',
        'expires_at'             => 'datetime',
        'converted_at'           => 'datetime',
        'recovery_sent_by'       => 'integer',
        'recovery_sent_at'       => 'datetime',
        'recovery_opened_at'     => 'datetime',
    ];

    // The columns' defaults, so one just made reads as it was saved.
    protected $attributes = [
        'status'    => self::ACTIVE,
        'marketing' => false,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }

    /** Its lines, in the order they were added. */
    public function lines(): HasMany
    {
        return $this->hasMany(EcommerceCartLine::class)->orderBy('id');
    }

    /** Phase H3a: the account its customer was signed in to at checkout, if they were. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(StorefrontCustomer::class, 'storefront_customer_id');
    }

    /** Phase H3a: the order it became, once converted. */
    public function order(): HasOne
    {
        return $this->hasOne(EcommerceOrder::class);
    }

    /** Phase H3b: whether it was converted after its recovery e-mail. */
    public function isRecovered(): bool
    {
        return $this->status === self::CONVERTED && $this->recovery_sent_at !== null && $this->converted_at?->gte($this->recovery_sent_at) === true;
    }
}
