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
 * cookie notice, and its words (none: izSuite's own). From Phase H3a, how
 * many hours a cart whose customer is known goes unused before it is
 * Abandoned; from Phase H4b, what a request for a quotation also becomes -
 * nothing more, a CRM lead while CRM is on, or (Phase H4c) a draft quotation;
 * from Phase H4d, whether a customer may attach files to one.
 */
class EcommerceSetting extends Model
{
    public const WITH_TAX = 'with_tax';

    public const WITHOUT_TAX = 'without_tax';

    public const PRICE_DISPLAYS = [self::WITH_TAX, self::WITHOUT_TAX];

    /** Phase D4: whether checkout asks for a phone number. */
    public const PHONE_FIELDS = ['required', 'optional', 'hidden'];

    /** Phase H4c: what a request for a quotation also becomes - nothing more, a CRM lead (while CRM is on) or a draft quotation. */
    public const BECOME_NOTHING = 'nothing';

    public const BECOME_LEAD = 'lead';

    public const BECOME_QUOTATION = 'quotation';

    public const QUOTE_REQUESTS_BECOME = [self::BECOME_NOTHING, self::BECOME_LEAD, self::BECOME_QUOTATION];

    protected $fillable = [
        'guest_checkout',
        'verify_customer_email',
        'price_display',
        'order_prefix',
        // Phase D3: how many days a cart is kept without activity.
        'cart_days',
        // Phase H3a: how many hours a cart whose customer is known goes unused before it is Abandoned.
        'abandoned_hours',
        // Phase H4b, a choice from H4c: what a request for a quotation also becomes (QUOTE_REQUESTS_BECOME).
        'quote_requests_become',
        // Phase H4d: whether a customer may attach files to a request for a quotation.
        'quote_request_files',
        // Phase D4: whether checkout asks for a phone number - required, optional or hidden - and a note.
        'checkout_phone',
        'checkout_notes',
        'cookie_notice',
        'cookie_notice_text',
        // Phase F4: Review Orders Above (base currency; none to start), and whether such orders are held as placed.
        'review_above',
        'hold_for_review',
        // Phase F6a: how many days a payment link works (7 to start), and whether one may ask less than is owed (off).
        'payment_link_days',
        'partial_payments',
        // Phase G3b: for how many days a delivered download works (30) and how many times each file (5) - null for no
        // limit - and whether downloading needs the customer signed in to their account (off).
        'download_days',
        'download_limit',
        'downloads_need_account',
    ];

    protected $casts = [
        'guest_checkout'        => 'boolean',
        'verify_customer_email' => 'boolean',
        'cookie_notice'         => 'boolean',
        'cart_days'             => 'integer',
        'abandoned_hours'       => 'integer',
        'quote_request_files'   => 'boolean',
        'checkout_notes'        => 'boolean',
        'review_above'          => 'decimal:2',
        'hold_for_review'       => 'boolean',
        'payment_link_days'     => 'integer',
        'partial_payments'      => 'boolean',
        'download_days'         => 'integer',
        'download_limit'        => 'integer',
        'downloads_need_account' => 'boolean',
    ];

    // The columns' defaults, so settings never saved read as they would be.
    protected $attributes = [
        'guest_checkout'        => true,
        'verify_customer_email' => true,
        'price_display'         => self::WITH_TAX,
        'order_prefix'          => 'EC-',
        'cart_days'             => 30,
        'abandoned_hours'       => 24,
        'quote_requests_become' => self::BECOME_LEAD,
        'quote_request_files'   => false,
        'checkout_phone'        => 'required',
        'checkout_notes'        => true,
        'cookie_notice'         => true,
        'hold_for_review'       => false,
        'payment_link_days'     => 7,
        'partial_payments'      => false,
        'download_days'         => 30,
        'download_limit'        => 5,
        'downloads_need_account' => false,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
