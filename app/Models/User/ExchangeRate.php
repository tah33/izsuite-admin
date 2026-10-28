<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one unit of another currency is worth in the business's base currency,
 * from a date on (Doc 11 section 7): 1 USD = 121.50 BDT from 2026-10-28. One
 * per currency and day; every one entered is kept, as the history.
 *
 * effective_date is left as the "2026-10-28" string the column holds - a date
 * cast would store a time with it on SQLite, and finding the rate in force on
 * a day would go wrong there.
 */
class ExchangeRate extends Model
{
    protected $fillable = [
        'currency',
        'base_currency',
        'rate',
        'effective_date',
        'created_by',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
    ];

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Who entered it. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
