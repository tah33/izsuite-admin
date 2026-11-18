<?php

namespace App\Models\Billing;

use App\Models\Concerns\BumpsCatalogVersion;
use App\Models\Frontend\Application;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A plan a business can be on: Billing Starter, Standard or Premium. Decides
 * which apps the business may turn on - not which ones are on - and its
 * limits (Doc 01 section 4): file storage (storage_limit_mb), users and
 * locations per business, and how many businesses on it one account may own.
 * Empty is no limit. All of it is set in the admin, never in the code (Doc 13
 * section 13.2); see PlanLimits for the counting.
 */
class Package extends Model
{
    use BumpsCatalogVersion;

    /** The plan every business starts on, and falls back to (Doc 01 section 13). */
    public const STARTER = 'starter';

    /** The reports and the support a plan gives (Doc 15 section 15.8 - Phase E3a). */
    public const REPORTING_LEVELS = ['basic' => 'Basic', 'standard' => 'Standard', 'advanced' => 'Advanced'];

    public const SUPPORT_LEVELS   = ['email' => 'E-mail', 'priority' => 'Priority', 'dedicated' => 'Dedicated'];

    protected $fillable = [
        'name',
        'description',
        'storage_limit_mb',
        'business_limit',
        'user_limit',
        'location_limit',
        // Its main currency: what a business pays in when the plan has no price in the business's own (Doc 15 Phase C3a).
        'price_currency',
        'trial_days',
        'grace_days',
        'removes_branding',
        // Doc 15 Phase E3a: its levels, the cycles it is offered on, and the billing countries (none: all).
        'reporting_level',
        'support_level',
        'offers_monthly',
        'offers_yearly',
        'countries',
        'is_active',
    ];

    protected $casts = [
        'rank'             => 'integer',
        'storage_limit_mb' => 'integer',
        'business_limit'   => 'integer',
        'user_limit'       => 'integer',
        'location_limit'   => 'integer',
        'trial_days'       => 'integer',
        'grace_days'       => 'integer',
        'removes_branding' => 'boolean',
        'offers_monthly'   => 'boolean',
        'offers_yearly'    => 'boolean',
        'countries'        => 'array',
        'is_active'        => 'boolean',
    ];

    /** Billing Starter, free forever (Doc 13 section 2). */
    public function isFree(): bool
    {
        return $this->key === self::STARTER;
    }

    /** Whether it is offered on $cycle - monthly or yearly (Doc 15 Phase E3a). */
    public function offersCycle(string $cycle): bool
    {
        return $cycle === WorkspaceSubscription::YEARLY ? (bool) ($this->offers_yearly ?? true) : (bool) ($this->offers_monthly ?? true);
    }

    /** @return list<string> the cycles it is offered on */
    public function cycles(): array
    {
        return array_values(array_filter(WorkspaceSubscription::CYCLES, fn (string $cycle) => $this->offersCycle($cycle)));
    }

    /** Offered to a business billed in $country: every country, unless it names some - an unknown country, only then. */
    public function offeredIn(?string $country): bool
    {
        return empty($this->countries) || ($country !== null && in_array($country, $this->countries, true));
    }

    /** The app ids, asked for once per instance. */
    private ?Collection $appIds = null;

    public function apps(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'package_apps', 'package_id', 'app_id');
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /** Its prices in each currency and cycle, past, present and to come (Doc 15 Phase C3a). */
    public function prices(): HasMany
    {
        return $this->hasMany(PackagePrice::class);
    }

    /** Its terms by version, past, present and to come (Doc 15 Phase E3b). */
    public function versions(): HasMany
    {
        return $this->hasMany(PackageVersion::class);
    }

    /** Whether a business on this plan may turn $app on. */
    public function allows(Application $app): bool
    {
        $this->appIds ??= $this->apps()->pluck('apps.id');

        return $this->appIds->contains($app->id);
    }

    public static function starter(): ?self
    {
        return static::where('key', self::STARTER)->first();
    }
}
