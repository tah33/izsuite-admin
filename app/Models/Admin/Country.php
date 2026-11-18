<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A country (Doc 15 section 15.19 - Phase F3a): its ISO code, name and calling
 * code, and the defaults a new business there takes - its currency, time zone
 * and regional settings, and what its addresses call their parts and how they
 * are written - as its current version has them. Read through Countries.
 */
class Country extends Model
{
    /** The defaults a new business there takes, which change by version from a day. */
    public const DEFAULTS = [
        'currency',
        'timezone',
        'date_format',
        'time_format',
        'number_format',
        'week_start',
        'paper_size',
        'fiscal_year_start_month',
        'region_label',
        'postal_label',
        'address_format',
    ];

    protected $fillable = [
        'code',
        'name',
        'calling_code',
        'currency',
        'timezone',
        'date_format',
        'time_format',
        'number_format',
        'week_start',
        'paper_size',
        'fiscal_year_start_month',
        'region_label',
        'postal_label',
        'address_format',
    ];

    protected $casts = [
        'fiscal_year_start_month' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(CountryVersion::class);
    }
}
