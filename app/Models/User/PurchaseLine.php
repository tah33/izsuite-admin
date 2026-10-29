<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a purchase: an item, or a description typed in for the one
 * purchase (Doc 09 section 6) - which never becomes an item. Its name,
 * description, unit and price are its own from the moment it is added, and
 * its tax is kept as it was then - with whether it is recoverable - so
 * changing the item or the rate later never changes the line. Recording a
 * purchase never changes its item's purchase price (section 21).
 */
class PurchaseLine extends Model
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
        'tax_recoverability',
        'tax_amount',
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
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** The rate its tax came from, as that rate is now. */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
