<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an expense (Doc 10 section 5): a category, what it was for, a
 * quantity - one unless told - and a unit amount (unit_price), more than zero
 * each. Its category's name and its tax are kept as they were when the line
 * was saved - with whether the tax is recoverable - so renaming the category
 * or changing the rate later never changes a recorded expense.
 */
class ExpenseLine extends Model
{
    protected $fillable = [
        'position',
        'expense_category_id',
        'category_name',
        'description',
        'quantity',
        'unit_price',
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
        'position'            => 'integer',
        'expense_category_id' => 'integer',
        'quantity'            => 'decimal:3',
        'unit_price'          => 'decimal:2',
        'amount'              => 'decimal:2',
        'tax_rate_id'         => 'integer',
        'tax_rate'            => 'decimal:4',
        'tax_amount'          => 'decimal:2',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** The rate its tax came from, as that rate is now. */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
