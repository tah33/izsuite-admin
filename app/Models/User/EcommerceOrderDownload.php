<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One download of a delivered digital product's file by its customer (Doc 19
 * section 19.28 - Phase G3b): which file - by name too, as it may be removed
 * from its item since - the account it was signed in to, if any, from where,
 * and when. A download counts against its product's limit from the moment
 * the count was last started (EcommerceOrderDelivery::$counted_from).
 */
class EcommerceOrderDownload extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'ecommerce_order_delivery_id',
        'workspace_file_id',
        'file_name',
        'storefront_customer_id',
        'ip_address',
        'user_agent',
        'downloaded_at',
    ];

    protected $casts = [
        'ecommerce_order_delivery_id' => 'integer',
        'workspace_file_id'           => 'integer',
        'storefront_customer_id'      => 'integer',
        'downloaded_at'               => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrderDelivery::class, 'ecommerce_order_delivery_id');
    }
}
