<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A visitor's cart on a storefront (Doc 19 section 19.14 - Phase D3), known
 * by a token their browser keeps - only its hash is kept here. Active while
 * it is used; Expired after Settings → eCommerce's days without activity;
 * Converted once its order is placed (Phase D4); Abandoned is Phase H's.
 *
 * It is not an order or a sale, and reserves nothing. Its currency and
 * totals are as it was last priced, the way the store shows prices - for
 * the business's lists of carts later (Phase H): what it shows its visitor
 * is always priced again.
 */
class EcommerceCart extends Model
{
    public const ACTIVE = 'active';

    public const CONVERTED = 'converted';

    public const ABANDONED = 'abandoned';

    public const EXPIRED = 'expired';

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
    ];

    protected $casts = [
        'storefront_id'    => 'integer',
        'subtotal'         => 'decimal:2',
        'tax_total'        => 'decimal:2',
        'grand_total'      => 'decimal:2',
        'last_activity_at' => 'datetime',
        'expires_at'       => 'datetime',
        'converted_at'     => 'datetime',
    ];

    // The column's default, so one just made reads as it was saved.
    protected $attributes = [
        'status' => self::ACTIVE,
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
}
