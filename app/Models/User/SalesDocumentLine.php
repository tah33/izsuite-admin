<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a sales document: an item, or a description typed in for the
 * one document (Doc 08 section 4) - which never becomes an item. Its name,
 * description, unit and price are its own from the moment it is added, and its
 * tax is kept as it was then (tax_name, tax_code, tax_rate, tax_treatment):
 * changing the item or the rate later never changes the line.
 *
 * A combo whose display shows its components keeps them (components), and a
 * physical product its warranty (warranty), as they were (Doc 04 sections 11
 * and 14).
 */
class SalesDocumentLine extends Model
{
    protected $fillable = [
        'position',
        'item_id',
        'name',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'amount',
        'tax_rate_id',
        'tax_name',
        'tax_code',
        'tax_rate',
        'tax_treatment',
        'tax_amount',
        'components',
        'warranty',
    ];

    protected $casts = [
        'position'        => 'integer',
        'item_id'         => 'integer',
        'quantity'        => 'decimal:3',
        'unit_price'      => 'decimal:2',
        'discount_value'  => 'decimal:4',
        'discount_amount' => 'decimal:2',
        'amount'          => 'decimal:2',
        'tax_rate_id'     => 'integer',
        'tax_rate'        => 'decimal:4',
        'tax_amount'      => 'decimal:2',
        'components'      => 'array',
        'warranty'        => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** The rate its tax was taken from, as it is now - for checking it is still in effect. */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** The tax as SalesCalculator takes it - null for a line without one. */
    public function taxForCalculation(): ?array
    {
        return $this->tax_rate_id || $this->tax_name !== null ? [
            'id'        => $this->tax_rate_id,
            'name'      => $this->tax_name,
            'code'      => $this->tax_code,
            'rate'      => (string) $this->tax_rate,
            'treatment' => $this->tax_treatment,
        ] : null;
    }
}
