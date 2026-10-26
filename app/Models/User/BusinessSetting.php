<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's profile and defaults (Settings > Business). Its logo is the
 * workspace's file in the "logo" collection, not a column here.
 */
class BusinessSetting extends Model
{
    /** What the stock_accounting_method column can hold - the enum in the migration. */
    public const STOCK_ACCOUNTING_METHODS = ['fifo', 'lifo', 'weighted_average', 'specific_identification'];

    protected $fillable = [
        'user_id',
        'workspace_id',
        'business_name',
        'start_date',
        'language',
        'website',
        'business_contact_number',
        'alternate_contact_number',
        'country',
        'state',
        'city',
        'zip_code',
        'landmark',
        'timezone',
        'tax_1_name',
        'tax_1_no',
        'tax_2_name',
        'tax_2_no',
        'financial_year_start_month',
        'stock_accounting_method',
    ];

    protected $casts    = [
        'start_date'                 => 'date',
        'financial_year_start_month' => 'integer',
    ];

    // ── Relationships ──

    /** The account that owns the business. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The business it belongs to. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
