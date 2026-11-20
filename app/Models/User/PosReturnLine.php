<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line of a POS return (Doc 18 Phase E1): the sale's line it brings back, how much of it, in what condition, and what it credits. */
class PosReturnLine extends Model
{
    protected $fillable = [
        'sales_document_line_id',
        'quantity',
        'condition',
        'amount',
    ];

    protected $casts = [
        'sales_document_line_id' => 'integer',
        'quantity'               => 'decimal:3',
        'amount'                 => 'decimal:2',
    ];

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SalesDocumentLine::class, 'sales_document_line_id');
    }
}
