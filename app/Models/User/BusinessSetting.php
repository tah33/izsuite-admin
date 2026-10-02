<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSetting extends Model
{
    /** What the stock_accounting_method column can hold - the enum in the migration. */
    public const STOCK_ACCOUNTING_METHODS = ['fifo', 'lifo', 'weighted_average', 'specific_identification'];

    protected $fillable = [
        'user_id',
        'business_name',
        'start_date',
        'currency',
        'language',
        'logo',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
