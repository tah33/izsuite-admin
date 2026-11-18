<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much of a sales order line came back on a return (Doc 16 Phase E), with the item's warranty as the order kept it. */
class SalesReturnLine extends Model
{
    protected $fillable = [
        'sales_document_line_id',
        'quantity',
        'warranty',
    ];

    protected $casts = [
        'sales_document_line_id' => 'integer',
        'quantity'               => 'decimal:3',
        'warranty'               => 'array',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /** The order line it returns some of. */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SalesDocumentLine::class, 'sales_document_line_id');
    }
}
