<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of an online order's digital products delivered, or a service
 * performed (Doc 19 sections 19.28 and 19.29 - Phase G3a), one for each of
 * the order's products that do not ship. A digital product is delivered as
 * its item said when the order was paid - a download or an external link at
 * once, a license key or a delivery by hand once the business gives it, so
 * Waiting until then - and may be Revoked by hand, then given again. A
 * service is Performed on a day. The license keys are kept encrypted.
 */
class EcommerceOrderDelivery extends Model
{
    public const DIGITAL = 'digital';

    public const SERVICE = 'service';

    public const DOWNLOAD = 'download';

    public const EXTERNAL_LINK = 'external_link';

    public const LICENSE_KEY = 'license_key';

    public const MANUAL = 'manual';

    /** Delivered as soon as the order is paid. */
    public const AT_ONCE = [self::DOWNLOAD, self::EXTERNAL_LINK];

    public const WAITING = 'waiting';

    public const DELIVERED = 'delivered';

    public const REVOKED = 'revoked';

    public const PERFORMED = 'performed';

    /** Where a product without a delivery stands: a digital one waiting for the order to be paid, a service to be performed. */
    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const TO_PERFORM = 'to_perform';

    protected $fillable = [
        'workspace_id',
        'ecommerce_order_id',
        'ecommerce_order_line_id',
        'kind',
        'method',
        'status',
        'license_keys',
        'note',
        'delivered_at',
        'performed_on',
        'delivered_by',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
    ];

    protected $casts = [
        'ecommerce_order_id'      => 'integer',
        'ecommerce_order_line_id' => 'integer',
        'license_keys'            => 'encrypted:array',
        'delivered_at'            => 'datetime',
        'performed_on'            => 'date:Y-m-d',
        'delivered_by'            => 'integer',
        'revoked_at'              => 'datetime',
        'revoked_by'              => 'integer',
    ];

    /** Delivered, revoked after it was, or performed: it reached the customer. */
    public function isDone(): bool
    {
        return in_array($this->status, [self::DELIVERED, self::REVOKED, self::PERFORMED], true);
    }

    /**
     * Where $order's product $line stands: its $delivery's status, else a
     * service to be performed, or a digital product waiting for the order to
     * be paid in full - or, paid, for the business.
     */
    public static function stateOf(EcommerceOrder $order, EcommerceOrderLine $line, ?self $delivery): string
    {
        return $delivery?->status ?? match (true) {
            $line->isService()                                   => self::TO_PERFORM,
            $order->payment_status === EcommerceOrder::PAID      => self::WAITING,
            default                                              => self::AWAITING_PAYMENT,
        };
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrderLine::class, 'ecommerce_order_line_id');
    }

    /** Who delivered it, or performed the service. */
    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
