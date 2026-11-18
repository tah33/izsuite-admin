<?php

namespace App\Models\Frontend;

use App\Enums\Edition;
use App\Models\Billing\Package;
use App\Models\Concerns\BumpsCatalogVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An app in the catalog: one edition (Lite or Full) of one app family.
 *
 * Check apps in code by family and edition - Sales, Full - never by app_key:
 * URLs, business records and access checks all use the family, so both
 * editions share them (see the "izSuite Apps & Menus" plan).
 */
class Application extends Model
{
    use BumpsCatalogVersion, HasFactory;

    protected $table = 'apps';

    public const DRAFT       = 'draft';

    public const COMING_SOON = 'coming_soon';

    /** Published: "available" as stored. */
    public const PUBLISHED   = 'available';

    public const UNAVAILABLE = 'unavailable';

    public const DEPRECATED  = 'deprecated';

    public const RETIRED     = 'retired';

    /**
     * The catalog's own states (Doc 15 section 15.10 - Phase E2). Whether an
     * app is on for a business is kept per business, in workspace_apps.
     *
     *   Draft                    not listed; not ready
     *   Coming Soon              listed; cannot be turned on yet
     *   Published                listed; can be turned on
     *   Temporarily Unavailable  listed; not turned on now - those with it keep it
     *   Deprecated               listed; not turned on any more - those with it keep it
     *   Retired                  not listed; off for everyone, its records kept
     */
    public const STATUSES = [
        self::DRAFT       => 'Draft',
        self::COMING_SOON => 'Coming Soon',
        self::PUBLISHED   => 'Published',
        self::UNAVAILABLE => 'Temporarily Unavailable',
        self::DEPRECATED  => 'Deprecated',
        self::RETIRED     => 'Retired',
    ];

    /** Not in the Apps Center or on the website, whatever its Listed switch says. */
    public const HIDDEN = [self::DRAFT, self::RETIRED];

    protected $fillable = [
        'app_key',
        'name',
        'description',
        'long_description',
        'features',
        'support_url',
        'price',
        'logo_url',
        'category_id',
        'icon',
        'status',
        'countries',
        'conflicts',
        'release_version',
        'is_active',
        'family_key',
        'edition',
        'sort_order',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'edition'    => Edition::class,
        'features'   => 'array',
        'countries'  => 'array',
        'conflicts'  => 'array',
    ];

    /**
     * A standalone app is a family of one, so family_key defaults to the name's
     * slug; app_key to the family, with _lite for a Lite edition.
     */
    protected static function booted(): void
    {
        static::saving(function (self $app) {
            if (blank($app->family_key)) {
                $app->family_key = Str::slug((string) $app->name);
            }

            if (blank($app->app_key)) {
                $app->app_key = $app->family_key.($app->edition === Edition::Lite ? '_lite' : '');
            }
        });
    }

    // ── Relationships ──

    public function category(): BelongsTo
    {
        return $this->belongsTo(AppCategory::class, 'category_id');
    }

    /** The families this app needs turned on before it can be. */
    public function dependencies(): HasMany
    {
        return $this->hasMany(AppDependency::class, 'app_id');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_apps', 'app_id', 'package_id');
    }

    // ── Families ──

    /**
     * Every edition of one family, Lite first.
     */
    public function scopeInFamily(Builder $query, string $familyKey): Builder
    {
        return $query->where('family_key', $familyKey)
            ->orderByRaw("case when edition = 'lite' then 1 else 2 end");
    }

    /**
     * The other editions of this app's family, Lite first.
     */
    public function siblings(): Collection
    {
        return static::inFamily($this->family_key)->whereKeyNot($this->getKey())->get();
    }

    /**
     * Active editions this one can be upgraded to - for a Lite app, its Full one.
     */
    public function upgrades(): Collection
    {
        return $this->siblings()
            ->filter(fn (self $app) => $app->is_active && $app->edition->rank() > $this->edition->rank())
            ->values();
    }

    /** The Lite edition of this app's family, if there is one. */
    public function liteEdition(): ?self
    {
        return $this->edition === Edition::Lite
            ? $this
            : static::where('family_key', $this->family_key)->where('edition', Edition::Lite)->first();
    }

    /** Of a family's editions, the one that names its card: Full, else the only one. */
    public static function primaryOf(\Illuminate\Support\Collection $editions): self
    {
        return $editions->sortByDesc(fn (self $app) => $app->edition->rank())->first();
    }

    /** Listed, and published: an app a business could turn on. */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === self::PUBLISHED;
    }

    /** In the Apps Center and on the website: listed, and neither a draft nor retired. */
    public function isListed(): bool
    {
        return $this->is_active && ! in_array($this->status, self::HIDDEN, true);
    }

    /** Offered to a business in $country (a code): every country, unless it names some. */
    public function offeredIn(?string $country): bool
    {
        return empty($this->countries) || in_array($country, $this->countries, true);
    }

    /** Whether it cannot be on beside $other - either naming the other's family. */
    public function conflictsWith(self $other): bool
    {
        return $other->family_key !== $this->family_key
            && (in_array($other->family_key, $this->conflicts ?? [], true) || in_array($this->family_key, $other->conflicts ?? [], true));
    }

    /** The apps in the Apps Center and on the website: listed, and neither a draft nor retired. */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotIn('status', self::HIDDEN);
    }

    public function editionLabel(): ?string
    {
        return $this->edition?->label();
    }
}
