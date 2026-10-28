<?php

namespace App\Models\User;

use App\Enums\TaxTreatment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a business's tax rates (Doc 11 section 10): VAT 15%, Exempt. Its code
 * is upper case and used once in the business. It is in effect from
 * effective_from to effective_to - open-ended when that is empty - and offered
 * only to what is dated inside that.
 *
 * It applies to sales, purchases, expenses or several of them. Recoverability
 * says whether purchase or expense tax can be claimed back; it means nothing
 * for a rate only used on sales.
 *
 * default_for_sales and default_for_purchases mark the business's default sales
 * and purchase tax (Doc 11 section 11): at most one of each, set through
 * TaxSettingService - so they are not fillable.
 */
class TaxRate extends Model
{
    public const SALES = 'sales';

    public const PURCHASES = 'purchases';

    public const EXPENSES = 'expenses';

    /** What a rate can apply to - and the column that says it does: for_sales... */
    public const SIDES = [self::SALES, self::PURCHASES, self::EXPENSES];

    /** The sides a business has a default tax for. */
    public const DEFAULT_SIDES = [self::SALES, self::PURCHASES];

    public const RECOVERABILITY = ['recoverable', 'non_recoverable'];

    protected $fillable = [
        'name',
        'code',
        'rate',
        'treatment',
        'for_sales',
        'for_purchases',
        'for_expenses',
        'recoverability',
        'effective_from',
        'effective_to',
        'description',
        'is_active',
    ];

    protected $attributes = [
        'for_sales'             => false,
        'for_purchases'         => false,
        'for_expenses'          => false,
        'is_active'             => true,
        'default_for_sales'     => false,
        'default_for_purchases' => false,
    ];

    /**
     * effective_from and effective_to are left as the "2026-10-28" strings the
     * columns hold: a date cast would store them with a time on SQLite, and
     * comparing them with a document's date would go wrong there.
     */
    protected $casts = [
        'rate'                  => 'decimal:4',
        'treatment'             => TaxTreatment::class,
        'for_sales'             => 'boolean',
        'for_purchases'         => 'boolean',
        'for_expenses'          => 'boolean',
        'is_active'             => 'boolean',
        'default_for_sales'     => 'boolean',
        'default_for_purchases' => 'boolean',
    ];

    /** What it applies to: ['sales', 'purchases']. */
    public function appliesTo(): array
    {
        return array_values(array_filter(self::SIDES, fn (string $side) => $this->{"for_{$side}"}));
    }

    /** What it is the business's default tax for: ['sales']. */
    public function defaultFor(): array
    {
        return array_values(array_filter(self::DEFAULT_SIDES, fn (string $side) => $this->{"default_for_{$side}"}));
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
