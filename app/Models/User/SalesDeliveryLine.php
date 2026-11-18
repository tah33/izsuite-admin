<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much of one sales order line a delivery takes (Doc 16 section 16.10). */
class SalesDeliveryLine extends Model
{
    protected $fillable = [
        'sales_document_line_id',
        'quantity',
    ];

    protected $casts = [
        'sales_document_line_id' => 'integer',
        'quantity'               => 'decimal:3',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(SalesDelivery::class, 'sales_delivery_id');
    }

    /** The order's line it delivers. */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SalesDocumentLine::class, 'sales_document_line_id');
    }
}
