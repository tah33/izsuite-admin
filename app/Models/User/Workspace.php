<?php

namespace App\Models\User;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Services\User\Apps\ProvisionWorkspaceApps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A business workspace: the business's plan, the apps it has turned on, its
 * base currency and tax choice, and its own data - locations, settings, staff,
 * contacts, items and payment accounts - kept apart from the owner's other
 * businesses. Deleting it deletes that data too.
 *
 * Only name and user_id are fillable: the plan changes through
 * SyncAppsWithPackage's callers, nav_version only ever goes up by one, and the
 * currencies and tax choice through BusinessSetupService.
 */
class Workspace extends Model
{
    /** Taxes are used: tax fields show (Doc 01 section 12). */
    public const TAXES_ENABLED = 'enabled';

    /** Taxes are not used: tax fields are hidden, never deleted. */
    public const TAXES_DISABLED = 'disabled';

    /** Not decided yet - "Set Up Later". Tax fields stay hidden meanwhile. */
    public const TAXES_LATER = 'later';

    public const TAX_MODES = [self::TAXES_ENABLED, self::TAXES_DISABLED, self::TAXES_LATER];

    protected $fillable = [
        'name',
        'user_id',
    ];

    protected $casts = [
        'nav_version'           => 'integer',
        'additional_currencies' => 'array',
        'brands_enabled'        => 'boolean',
    ];

    /**
     * Every new business starts on Billing Starter (Doc 01 section 3), with the
     * Lite apps of its plan turned on, and on the platform's default currency.
     */
    protected static function booted(): void
    {
        static::creating(function (self $workspace) {
            $workspace->package_id ??= Package::where('key', Package::STARTER)->value('id');
            $workspace->base_currency ??= Currency::active()->default()->value('code') ?? 'USD';
            $workspace->tax_mode ??= self::TAXES_LATER;
        });

        static::created(fn (self $workspace) => app(ProvisionWorkspaceApps::class)->handle($workspace));

        // The cached sidebar carries the business's name, currencies, tax
        // choice and whether it uses brands, so a change to any of them needs
        // a fresh one.
        static::updating(function (self $workspace) {
            if ($workspace->isDirty(['name', 'base_currency', 'additional_currencies', 'tax_mode', 'brands_enabled'])) {
                $workspace->nav_version = $workspace->nav_version + 1;
            }
        });
    }

    /** The codes it can take or pay in: the base currency first, then the others. */
    public function currencyCodes(): array
    {
        return array_values(array_unique([$this->base_currency, ...($this->additional_currencies ?? [])]));
    }

    public function usesTaxes(): bool
    {
        return $this->tax_mode === self::TAXES_ENABLED;
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** One row per app family the business has turned on, now or before. */
    public function workspaceApps(): HasMany
    {
        return $this->hasMany(WorkspaceApp::class);
    }

    public function appLogs(): HasMany
    {
        return $this->hasMany(WorkspaceAppLog::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function businessSetting(): HasOne
    {
        return $this->hasOne(BusinessSetting::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(UserStaff::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function itemCategories(): HasMany
    {
        return $this->hasMany(ItemCategory::class);
    }

    public function itemBrands(): HasMany
    {
        return $this->hasMany(ItemBrand::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    /**
     * Only the owner can open a business for now: staff are records without a
     * login. When staff can sign in, their access is decided here.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
