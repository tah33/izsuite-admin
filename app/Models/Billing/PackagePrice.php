<?php

namespace App\Models\Billing;

use App\Models\Concerns\BumpsCatalogVersion;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A plan's price in one currency for one cycle (Doc 15 sections 15.8 and
 * 15.9 - Phase C3a): from its start day until the day before the next one
 * of its currency and cycle starts. Kept once it has started - history -
 * while one not started yet can be removed.
 */
class PackagePrice extends Model
{
    // The catalog shows prices: a change reaches every business's next request.
    use BumpsCatalogVersion;

    public const SCHEDULED = 'scheduled';

    public const CURRENT   = 'current';

    public const ENDED     = 'ended';

    protected $fillable = [
        'package_id',
        'currency',
        'cycle',
        'amount',
        'starts_on',
        'ends_on',
        'created_by',
    ];

    protected $casts    = [
        'amount'    => 'decimal:2',
        'starts_on' => 'date',
        'ends_on'   => 'date',
    ];

    /** Where it stands on $day: not started yet, in force, or followed by another. */
    public function statusOn(Carbon|string $day): string
    {
        $day = Carbon::parse($day)->startOfDay();

        return match (true) {
            $this->starts_on->gt($day)                    => self::SCHEDULED,
            $this->ends_on !== null && $this->ends_on->lt($day) => self::ENDED,
            default                                       => self::CURRENT,
        };
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
