<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's profile and defaults (Settings > Business, Doc 11 section 4).
 * Its logo is the workspace's file in the "logo" collection, not a column
 * here; its country a code, as a contact's is.
 */
class BusinessSetting extends Model
{
    /** What the stock_accounting_method column can hold - the enum in the migration. */
    public const STOCK_ACCOUNTING_METHODS = ['fifo', 'lifo', 'weighted_average', 'specific_identification'];

    /** The business types, or legal structures, a business may say it is. */
    public const LEGAL_STRUCTURES = [
        'sole_proprietorship', 'partnership', 'private_limited', 'public_limited', 'limited_liability_partnership',
        'limited_liability_company', 'cooperative', 'non_profit', 'other',
    ];

    protected $fillable = [
        'user_id',
        'workspace_id',
        'business_name',
        'trading_name',
        'registration_number',
        'legal_structure',
        'industry',
        'start_date',
        'language',
        'website',
        'business_contact_number',
        'alternate_contact_number',
        'email',
        'address_line_1',
        'address_line_2',
        'country',
        'state',
        'city',
        'zip_code',
        'timezone',
        'tax_1_name',
        'tax_1_no',
        'tax_2_name',
        'tax_2_no',
        'stock_accounting_method',
    ];

    protected $casts    = [
        'start_date'                 => 'date',
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
