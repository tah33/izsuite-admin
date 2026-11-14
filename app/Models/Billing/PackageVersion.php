<?php

namespace App\Models\Billing;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A version of a plan's terms (Doc 15 section 15.8 - Phase E3b): its
 * limits, branding, levels, trial and grace from its day. When it starts
 * (applied_at), the plan takes them, and a business subscribed to the plan
 * stays on the version it was on. Kept after another follows, for the
 * businesses still on it.
 */
class PackageVersion extends Model
{
    /** A plan's terms that change by version. */
    public const TERMS = ['storage_limit_mb', 'business_limit', 'user_limit', 'location_limit', 'removes_branding', 'reporting_level', 'support_level', 'trial_days', 'grace_days'];

    /** Waiting for its day. */
    public const SCHEDULED = 'scheduled';

    /** The plan's terms now. */
    public const CURRENT   = 'current';

    /** Followed by another: kept for the businesses still on it. */
    public const EARLIER   = 'earlier';

    protected $fillable = [
        'package_id',
        'number',
        'starts_on',
        'applied_at',
        'storage_limit_mb',
        'business_limit',
        'user_limit',
        'location_limit',
        'removes_branding',
        'reporting_level',
        'support_level',
        'trial_days',
        'grace_days',
        'created_by',
    ];

    protected $casts = [
        'number'           => 'integer',
        'starts_on'        => 'date',
        'applied_at'       => 'datetime',
        'storage_limit_mb' => 'integer',
        'business_limit'   => 'integer',
        'user_limit'       => 'integer',
        'location_limit'   => 'integer',
        'removes_branding' => 'boolean',
        'trial_days'       => 'integer',
        'grace_days'       => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The businesses that stay on it. */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /** Scheduled, current - $current is the plan's current version - or earlier. */
    public function stateBeside(?self $current): string
    {
        return match (true) {
            $this->applied_at === null       => self::SCHEDULED,
            $this->id === $current?->id      => self::CURRENT,
            default                          => self::EARLIER,
        };
    }

    /** @return array<string, mixed> its terms, by the plan's column names */
    public function terms(): array
    {
        return $this->only(self::TERMS);
    }

    /** Whether $terms differ from $current's, each as the plan keeps it - "5" is 5, and an empty limit null. */
    public static function differ(array $terms, Package|self $current): bool
    {
        $kept = (new self)->forceFill(array_intersect_key($terms, array_flip(self::TERMS)));

        return collect(array_keys(array_intersect_key($terms, array_flip(self::TERMS))))
            ->contains(fn (string $term) => $kept->{$term} !== $current->{$term});
    }
}
