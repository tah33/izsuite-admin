<?php

namespace App\Models\User;

use App\Models\Concerns\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales order's delivery note (Doc 16 section 16.10): how much of its lines
 * went out, where to, carried by whom, and who received it on which day.
 * Draft, then Ready (numbered), Delivered or Cancelled. A delivered one that
 * left the order short can have a backorder: a new draft for the rest
 * (backorder_of). No stock moves until Inventory exists.
 */
class SalesDelivery extends Model
{
    // Its proof of delivery - a signed note, photos.
    use HasFiles;

    public const DRAFT = 'draft';

    public const READY = 'ready';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    /** Its own address, as a sales document keeps one: null for the order's. */
    public const ADDRESS_PARTS = ['line1', 'line2', 'city', 'state', 'postal_code', 'country'];

    protected $fillable = [
        'sales_document_id',
        'number',
        'status',
        'delivery_date',
        'location_id',
        'shipping_address',
        'carrier',
        'tracking_number',
        'vehicle',
        'recipient_name',
        'notes',
        'delivered_on',
        'status_reason',
        'backorder_of',
        'readied_at',
        'delivered_at',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => self::DRAFT,
    ];

    protected $casts = [
        'sales_document_id' => 'integer',
        'location_id'       => 'integer',
        'shipping_address'  => 'array',
        'backorder_of'      => 'integer',
        'readied_at'        => 'datetime',
        'delivered_at'      => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The sales order it delivers. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesDeliveryLine::class)->orderBy('id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** The delivery it is the backorder of. */
    public function backorderOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'backorder_of');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::DRAFT, self::READY], true);
    }
}
