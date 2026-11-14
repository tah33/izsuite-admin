<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A version of a country's defaults (Doc 15 section 15.19 - Phase F3a): the
 * currency, time zone, regional settings and address format a new business
 * there takes from its day. When it starts (applied_at), the country takes
 * them. Businesses already set up keep their own settings, and documents
 * already issued keep what they printed.
 */
class CountryVersion extends Model
{
    /** Waiting for its day. */
    public const SCHEDULED = 'scheduled';

    /** The country's defaults now. */
    public const CURRENT   = 'current';

    /** Followed by another. */
    public const EARLIER   = 'earlier';

    protected $fillable = [
        'country_id',
        'number',
        'starts_on',
        'applied_at',
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
        'created_by',
    ];

    protected $casts = [
        'number'                  => 'integer',
        'starts_on'               => 'date',
        'applied_at'              => 'datetime',
        'fiscal_year_start_month' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Scheduled, current - $current is the country's current version - or earlier. */
    public function stateBeside(?self $current): string
    {
        return match (true) {
            $this->applied_at === null  => self::SCHEDULED,
            $this->id === $current?->id => self::CURRENT,
            default                     => self::EARLIER,
        };
    }

    /** @return array<string, mixed> its defaults, by the country's column names */
    public function defaults(): array
    {
        return $this->only(Country::DEFAULTS);
    }

    /** Whether $defaults differ from $country's, each as the country keeps it - "7" is 7, and an empty one null. */
    public static function differ(array $defaults, Country $country): bool
    {
        $given = array_intersect_key($defaults, array_flip(Country::DEFAULTS));
        $kept  = (new self)->forceFill($given);

        return collect(array_keys($given))->contains(fn (string $field) => $kept->{$field} !== $country->{$field});
    }
}
