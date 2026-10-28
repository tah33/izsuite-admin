<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Settings > Sales (Doc 11 section 15): what a new quotation, proforma invoice
 * or invoice starts with. Changing it leaves documents already made as they
 * are. A business has none until it first saves them; an unsaved one reads the
 * defaults, the same as the columns'.
 */
class SalesSetting extends Model
{
    protected $fillable = [
        'payment_terms',
        'quotation_validity',
        'proforma_validity',
        'line_discounts',
        'document_discounts',
        'notes',
        'terms',
    ];

    protected $attributes = [
        'payment_terms'      => 0,
        'quotation_validity' => 30,
        'proforma_validity'  => 30,
        'line_discounts'     => true,
        'document_discounts' => true,
    ];

    protected $casts = [
        'payment_terms'      => 'integer',
        'quotation_validity' => 'integer',
        'proforma_validity'  => 'integer',
        'line_discounts'     => 'boolean',
        'document_discounts' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
