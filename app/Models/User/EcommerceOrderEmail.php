<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One e-mail of an online order to its customer (Doc 19 section 19.35 -
 * Phase D5), kept with the order as its history: its kind, to whom, and
 * whether it went - queued, sent, or failed after its tries, with why.
 */
class EcommerceOrderEmail extends Model
{
    public const ORDER_RECEIVED = 'order_received';

    /** Phase F2: the order confirmed, with its invoice's link. */
    public const ORDER_CONFIRMED = 'order_confirmed';

    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = [
        'kind',
        'to',
        'status',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }
}
