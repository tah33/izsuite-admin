<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What POS keeps of a line of its order's invoice (Doc 18 section 18.12 -
 * Phase C2): where its price came from - the item, the register's price
 * list or the customer's own - or, overridden (Phase C3), the price it
 * replaced and why.
 */
class PosOrderLine extends Model
{
    public const OVERRIDE = 'override';

    protected $fillable = [
        'sales_document_line_id',
        'price_source',
        'standard_price',
        'override_reason',
        // Phase I2: the manager who approved the override at the register.
        'override_approved_by',
    ];

    protected $casts = [
        'sales_document_line_id' => 'integer',
        'standard_price'         => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesDocumentLine::class, 'sales_document_line_id');
    }
}
