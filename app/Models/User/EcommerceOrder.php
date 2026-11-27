<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order placed on a storefront (Doc 19 sections 19.15 to 19.20 - Phase
 * D4): numbered by Settings → eCommerce's prefix (EC-00001), for one shared
 * contact - found or made at checkout - at its storefront's location, in its
 * currency at the day's rate.
 *
 * It keeps what was bought as it was then: the customer's details and
 * addresses as given, each line's product, price and where it came from,
 * its tax, the shipping, and how it is delivered and paid - each a snapshot,
 * so changing a contact, item, price, method or point later never reaches
 * it. Its three statuses - the order's, its payment's and its fulfilment's -
 * stay apart (19.19).
 *
 * Placed, it is Pending, its payment Unpaid (Pending once the customer gives
 * a payment's reference) and its fulfilment Unfulfilled - Not Required when
 * nothing is delivered. It is not a sale: its invoice is issued when it is
 * confirmed (Phase F2), and then it counts once. From Phase G1 its
 * shipments, or its readiness for pickup, move its fulfilment and make it
 * Processing, then Completed once delivered and paid (EcommerceOrderProgress).
 */
class EcommerceOrder extends Model
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    /** Phase F4: held for review, released back to where it was; or cancelled. */
    public const ON_HOLD = 'on_hold';

    public const CANCELLED = 'cancelled';

    /** Phase G1: being fulfilled - from its first shipment, or ready for pickup - and completed once delivered and paid. */
    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const STATUSES = ['pending', 'confirmed', 'on_hold', 'processing', 'completed', 'cancelled'];

    public const UNPAID = 'unpaid';

    public const PAYMENT_PENDING = 'pending';

    /** Phase F3: following its invoice once a payment is recorded, or marked failed. */
    public const PARTIALLY_PAID = 'partially_paid';

    public const PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_STATUSES = ['unpaid', 'pending', 'authorised', 'partially_paid', 'paid', 'failed', 'partially_refunded', 'refunded'];

    public const NOT_REQUIRED = 'not_required';

    public const UNFULFILLED = 'unfulfilled';

    /** Phase G1: as its shipments go out and arrive - or it is made ready, then collected. */
    public const PARTIALLY_FULFILLED = 'partially_fulfilled';

    public const READY_FOR_PICKUP = 'ready_for_pickup';

    public const FULFILLED = 'fulfilled';

    public const SHIPPED = 'shipped';

    public const DELIVERED = 'delivered';

    public const FULFILMENT_STATUSES = ['not_required', 'unfulfilled', 'partially_fulfilled', 'ready_for_pickup', 'fulfilled', 'shipped', 'delivered', 'partially_returned', 'returned'];

    /** How an order reaches its customer. */
    public const SHIPPING = 'shipping';

    public const PICKUP = 'pickup';

    public const NONE = 'none';

    /** Phase F5b: its discount - a percentage of its products, or an amount - in Sales' words. */
    public const PERCENT = 'percent';

    public const AMOUNT = 'amount';

    /** The workspace_sequences counter its numbers come from. */
    public const NUMBER_SEQUENCE = 'ecommerce_order';

    protected $fillable = [
        'storefront_id',
        'ecommerce_cart_id',
        'number',
        'contact_id',
        'contact_created',
        // Phase E2: the customer's account it is, when it is one's.
        'storefront_customer_id',
        'location_id',
        'status',
        'payment_status',
        'fulfilment_status',
        'currency',
        'exchange_rate',
        'price_tax_mode',
        'tax_rounding',
        'prices_include_tax',
        'subtotal',
        'shipping_total',
        'discount_total',
        'tax_total',
        'grand_total',
        'net_total',
        'base_grand_total',
        'taxes',
        'customer',
        'customer_email',
        'billing_address',
        'shipping_address',
        'delivery',
        'shipping_method_id',
        'pickup_point_id',
        'delivery_snapshot',
        'ecommerce_payment_method_id',
        'payment_snapshot',
        'payment_reference',
        'customer_note',
        'source',
        'campaign',
        'consents',
        'ip_address',
        'user_agent',
        'access_token_hash',
        'placed_at',
        // Phase F2: its invoice, issued as it was confirmed - when, and by whom.
        'sales_document_id',
        'confirmed_at',
        'confirmed_by',
        // Phase F3: how often its payment was marked failed, and why the last time.
        'payment_failures',
        'payment_failure_reason',
        // Phase F4: on hold, the status it goes back to; why it is on hold, or was cancelled.
        'held_status',
        'status_reason',
        // Phase F5b: its discount - a percentage of its products or an amount - and why it was given.
        'discount_type',
        'discount_value',
        'discount_reason',
        // Phase H1: the discount code it was placed with, and as it was then - its name, code, what it takes off and took.
        'ecommerce_discount_id',
        'promotion',
    ];

    /**
     * Phase F4: what may want a look at it - for its page in the business,
     * never its customer - worked out by EcommerceOrderReview. Not a column:
     * never saved.
     *
     * @var list<string>|null
     */
    public ?array $reviewNotes = null;

    /**
     * Phase G3b: whether its customer must be signed in to their account to
     * download its digital products (Settings → eCommerce) - for its own page
     * in the store, as the page's service sets it. Not a column: never saved.
     */
    public bool $downloadsNeedAccount = false;

    protected $casts = [
        'storefront_id'               => 'integer',
        'ecommerce_cart_id'           => 'integer',
        'contact_id'                  => 'integer',
        'contact_created'             => 'boolean',
        'storefront_customer_id'      => 'integer',
        'location_id'                 => 'integer',
        'exchange_rate'               => 'decimal:10',
        'prices_include_tax'          => 'boolean',
        'subtotal'                    => 'decimal:2',
        'shipping_total'              => 'decimal:2',
        'discount_value'              => 'decimal:4',
        'discount_total'              => 'decimal:2',
        'tax_total'                   => 'decimal:2',
        'grand_total'                 => 'decimal:2',
        'net_total'                   => 'decimal:2',
        'base_grand_total'            => 'decimal:2',
        'taxes'                       => 'array',
        'customer'                    => 'array',
        'billing_address'             => 'array',
        'shipping_address'            => 'array',
        'shipping_method_id'          => 'integer',
        'pickup_point_id'             => 'integer',
        'delivery_snapshot'           => 'array',
        'ecommerce_payment_method_id' => 'integer',
        'payment_snapshot'            => 'array',
        'campaign'                    => 'array',
        'consents'                    => 'array',
        'placed_at'                   => 'datetime',
        'sales_document_id'           => 'integer',
        'confirmed_at'                => 'datetime',
        'confirmed_by'                => 'integer',
        'payment_failures'            => 'integer',
        'ecommerce_discount_id'       => 'integer',
        'promotion'                   => 'array',
    ];

    protected $hidden = ['access_token_hash'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Phase F1: the location it sells from, as its storefront did when it was placed. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcommerceCart::class, 'ecommerce_cart_id');
    }

    /** Phase E2: the customer's account it is - placed signed in, or its e-mail's guest order once the account is verified. */
    public function customerAccount(): BelongsTo
    {
        return $this->belongsTo(StorefrontCustomer::class, 'storefront_customer_id');
    }

    /** Its lines in their order: the products, then the shipping. */
    public function lines(): HasMany
    {
        return $this->hasMany(EcommerceOrderLine::class)->orderBy('position')->orderBy('id');
    }

    /** Phase F2: the invoice its confirmation issued - through which it counts as a sale. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    /** Phase F2: who confirmed it. */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** Phase D5: its e-mails to its customer, the oldest first - its notification history. */
    public function emails(): HasMany
    {
        return $this->hasMany(EcommerceOrderEmail::class)->orderBy('id');
    }

    /** Phase F6a: its payment links, the newest first. */
    public function paymentLinks(): HasMany
    {
        return $this->hasMany(EcommercePaymentLink::class)->orderByDesc('id');
    }

    /** Phase G1: its shipments, or its readiness for pickup, in the order they were made. */
    public function fulfilments(): HasMany
    {
        return $this->hasMany(EcommerceFulfilment::class)->orderBy('sequence');
    }

    /** Phase G3a: its digital products delivered and its services performed - one for each line that does not ship, once it is. */
    public function deliveries(): HasMany
    {
        return $this->hasMany(EcommerceOrderDelivery::class);
    }

    /**
     * Phase H1: the discount code it was placed with, while its discount is
     * still the one the code took off - none once the business changed it
     * (Phase F5b), the code staying on record in `promotion`.
     */
    public function discountCode(): ?string
    {
        $promotion = $this->promotion;

        return $promotion !== null && $this->discount_type === self::AMOUNT && bccomp((string) $this->discount_value, (string) $promotion['off'], 2) === 0
            ? $promotion['code']
            : null;
    }
}
