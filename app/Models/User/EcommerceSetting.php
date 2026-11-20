<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Settings → eCommerce (Doc 19 section 19.37 - Phase A1): the business's own
 * rules for its storefronts - whether a guest may check out (19.15), whether
 * a customer's account waits for their e-mail to be verified (19.16),
 * whether prices are shown with tax or without it (19.13), and the prefix of
 * its online orders' numbers. From Phase C6b, whether its storefronts show a
 * cookie notice, and its words (none: izSuite's own).
 */
class EcommerceSetting extends Model
{
    public const WITH_TAX = 'with_tax';

    public const WITHOUT_TAX = 'without_tax';

    public const PRICE_DISPLAYS = [self::WITH_TAX, self::WITHOUT_TAX];

    protected $fillable = [
        'guest_checkout',
        'verify_customer_email',
        'price_display',
        'order_prefix',
        'cookie_notice',
        'cookie_notice_text',
    ];

    protected $casts = [
        'guest_checkout'        => 'boolean',
        'verify_customer_email' => 'boolean',
        'cookie_notice'         => 'boolean',
    ];

    // The columns' defaults, so settings never saved read as they would be.
    protected $attributes = [
        'guest_checkout'        => true,
        'verify_customer_email' => true,
        'price_display'         => self::WITH_TAX,
        'order_prefix'          => 'EC-',
        'cookie_notice'         => true,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
