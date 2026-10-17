<?php

namespace App\Models\Frontend;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory;

    protected $table = 'apps';

    /** Status options shown in the admin dropdown, keyed by stored value. */
    public const STATUSES = [
        'included'    => 'Included',
        'active'      => 'Active',
        'available'   => 'Available',
        'locked'      => 'Locked',
        'coming_soon' => 'Coming Soon',
    ];

    /** Edition tiers, keyed by stored value. Rank comes from sort_order, not this list. */
    public const EDITIONS = [
        'lite' => 'Lite',
        'pro'  => 'Pro',
        'max'  => 'Max',
    ];

    protected $fillable = [
        'name',
        'description',
        'price',
        'logo_url',
        'category',
        'status',
        'is_active',
        'family_key',
        'edition',
        'sort_order',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * A standalone app is a family of one: default family_key to the name's slug.
     */
    protected static function booted(): void
    {
        static::saving(function (self $app) {
            if (blank($app->family_key)) {
                $app->family_key = Str::slug((string) $app->name);
            }
        });
    }

    /**
     * Every edition of one product, lowest tier first.
     */
    public function scopeInFamily(Builder $query, string $familyKey): Builder
    {
        return $query->where('family_key', $familyKey)->orderBy('sort_order');
    }

    /**
     * The other editions of this app's product, lowest tier first.
     */
    public function siblings(): Collection
    {
        return static::inFamily($this->family_key)->whereKeyNot($this->getKey())->get();
    }

    /**
     * Active editions this app can be upgraded to, lowest tier first.
     */
    public function upgrades(): Collection
    {
        return static::inFamily($this->family_key)
            ->where('sort_order', '>', $this->sort_order)
            ->where('is_active', true)
            ->get();
    }

    public function editionLabel(): ?string
    {
        return $this->edition ? (self::EDITIONS[$this->edition] ?? ucfirst($this->edition)) : null;
    }
}
