<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of an online order's digital products delivered, or a service
 * performed (Doc 19 sections 19.28 and 19.29 - Phase G3a), one for each of
 * the order's products that do not ship. A digital product is delivered as
 * its item said when the order was paid - a download or an external link at
 * once, a license key or a delivery by hand once the business gives it, so
 * Waiting until then - and may be Revoked by hand, then given again. A
 * service is Performed on a day. The license keys are kept encrypted. From
 * Phase G3b a download keeps its terms - until when it works and how many
 * times each file, as Settings → eCommerce said when it was delivered or
 * given more - and its downloads.
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
        // Phase G3b: a download's terms - until when it works, how many times each file, and when its count starts.
        'expires_at',
        'download_limit',
        'counted_from',
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
        'expires_at'              => 'datetime',
        'download_limit'          => 'integer',
        'counted_from'            => 'datetime',
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

    /** Phase G3b: how many times its file $fileId was downloaded since its count started - from its downloads, loaded. */
    public function usedOf(int $fileId): int
    {
        return $this->downloads->filter(fn (EcommerceOrderDownload $download) => $download->workspace_file_id === $fileId
            && ($this->counted_from === null || $download->downloaded_at->greaterThanOrEqualTo($this->counted_from)))->count();
    }

    /** Phase G3b: how many more times its file $fileId may be downloaded - null for no limit. */
    public function leftOf(int $fileId): ?int
    {
        return $this->download_limit === null ? null : max(0, $this->download_limit - $this->usedOf($fileId));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    /** Phase G3b: its downloads, the latest first. */
    public function downloads(): HasMany
    {
        return $this->hasMany(EcommerceOrderDownload::class)->orderByDesc('downloaded_at')->orderByDesc('id');
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
