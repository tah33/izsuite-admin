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
 * which apps the business may turn on - not which ones are on - and how much
 * file storage it has (storage_limit_mb; empty is no limit). Both are set in
 * the admin, never in the code (Doc 13 section 13.2).
 */
class Package extends Model
{
    use BumpsCatalogVersion;

    /** The plan every business starts on, and falls back to (Doc 01 section 13). */
    public const STARTER = 'starter';

    protected $fillable = [
        'name',
        'description',
        'storage_limit_mb',
        'is_active',
    ];

    protected $casts = [
        'rank'             => 'integer',
        'storage_limit_mb' => 'integer',
        'is_active'        => 'boolean',
    ];

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
