<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A feature flag (Doc 15 section 15.12 - Phase E1): a feature the code asks
 * about by its key (Features::enabled()). Switched on, it is on for its
 * audience between its days: the businesses chosen, always; and businesses
 * in the countries and plans chosen (none chosen: all), a share of them at
 * a time - the same ones each time. Off, it is off everywhere, and nothing
 * made while it was on is removed.
 */
class FeatureFlag extends Model
{
    public const ON  = 'on';

    public const OFF = 'off';

    /** Where a flag stands, as the admin reads it. */
    public const STATES = [
        'off'       => 'Off',
        'scheduled' => 'Scheduled',
        'live'      => 'Live',
        'ended'     => 'Ended',
    ];

    protected $fillable = [
        'key',
        'name',
        'description',
        'status',
        'businesses',
        'countries',
        'packages',
        'percentage',
        'starts_at',
        'ends_at',
        'responsible_id',
        'created_by',
    ];

    protected $casts    = [
        'businesses' => 'array',
        'countries'  => 'array',
        'packages'   => 'array',
        'percentage' => 'integer',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    /** Off, scheduled (on, before its start), live, or ended (on, after its end). */
    public function stateAt(Carbon $now): string
    {
        return match (true) {
            $this->status !== self::ON                                => 'off',
            $this->starts_at !== null && $this->starts_at->gt($now)   => 'scheduled',
            $this->ends_at !== null && $this->ends_at->lte($now)      => 'ended',
            default                                                   => 'live',
        };
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
