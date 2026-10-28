<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An earlier version of a sent quotation or proforma invoice (Doc 08 section
 * 12), whole - its header, lines and totals - as it was before it was changed.
 * Read-only: only the document's current version can be sent or converted.
 */
class SalesDocumentRevision extends Model
{
    protected $fillable = [
        'revision',
        'snapshot',
        'created_by',
    ];

    protected $casts = [
        'revision' => 'integer',
        'snapshot' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    /** Who changed the document, making this the earlier version. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
