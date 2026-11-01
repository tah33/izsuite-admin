<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of a business's expense categories (Doc 10 section 7): Rent, Utilities.
 * One shared list per business, names used once. Its tax_rate_id is the tax
 * its lines start with - a default, changed on the line where needed. An
 * inactive category is offered to no new expense; one used on an expense is
 * never deleted, only switched off, and the expenses keep its name as it was.
 */
class ExpenseCategory extends Model
{
    /** What a business starts with (Doc 10 section 2) - its own to rename, switch off or add to. */
    public const STARTER = ['Rent', 'Utilities', 'Transport', 'Communication', 'Marketing', 'Office Costs', 'Professional Services', 'Bank Charges', 'Repairs', 'Meals and Travel'];

    protected $fillable = [
        'name',
        'description',
        'tax_rate_id',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'tax_rate_id' => 'integer',
        'is_active'   => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The tax its lines start with. */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** The expense lines it is on - any status. */
    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class);
    }
}
