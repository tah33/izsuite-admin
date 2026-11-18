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
 * physical product its warranty (warranty: period, unit, start_rule, terms),
 * as they were (Doc 04 sections 11, 14 and 15). A combo's warranty is a list
 * instead: one entry for each component with a warranty, its name added. A
 * credit note's line points at the invoice line it credits (source_line_id),
 * whose tax it reverses; a converted document's line at the one it came from
 * (Doc 16 Phase B). A sales order's line counts what was delivered, invoiced,
 * returned and credited of its quantity, the ordered one (section 16.28).
 */
class SalesDocumentLine extends Model
{
    protected $fillable = [
        'source_line_id',
        // A deduction of this deposit invoice (Doc 16 Phase D2): a negative line of a later invoice of its order.
        'deposit_invoice_id',
        // The price list and rule that priced it (Doc 16 Phase F1).
        'price_list_id',
        'price_list_rule_id',
        'position',
        'item_id',
        'name',
        'description',
        'quantity',
        'delivered_quantity',
        'invoiced_quantity',
        'returned_quantity',
        'credited_quantity',
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
        'source_line_id'  => 'integer',
        'deposit_invoice_id' => 'integer',
        'price_list_id'      => 'integer',
        'price_list_rule_id' => 'integer',
        'position'        => 'integer',
        'item_id'         => 'integer',
        'quantity'        => 'decimal:3',
        'delivered_quantity' => 'decimal:3',
        'invoiced_quantity'  => 'decimal:3',
        'returned_quantity'  => 'decimal:3',
        'credited_quantity'  => 'decimal:3',
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

    /** The deposit invoice this line deducts. */
    public function depositInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'deposit_invoice_id');
    }

    /** The price list that priced it. */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
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
