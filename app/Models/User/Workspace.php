<?php

namespace App\Models\User;

use App\Models\Billing\Package;
use App\Services\User\Apps\ProvisionWorkspaceApps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business workspace: the business's plan and the apps it has turned on.
 *
 * package_id and nav_version are not fillable: the plan changes through
 * SyncAppsWithPackage's callers, and nav_version only ever goes up by one.
 */
class Workspace extends Model
{
    protected $fillable = [
        'name',
        'user_id',
    ];

    protected $casts = [
        'nav_version' => 'integer',
    ];

    /**
     * Every new business starts on Billing Starter (Doc 01 section 3), with the
     * Lite apps of its plan turned on.
     */
    protected static function booted(): void
    {
        static::creating(function (self $workspace) {
            $workspace->package_id ??= Package::where('key', Package::STARTER)->value('id');
        });

        static::created(fn (self $workspace) => app(ProvisionWorkspaceApps::class)->handle($workspace));

        // The cached sidebar carries the business's name, so a rename needs a fresh one.
        static::updating(function (self $workspace) {
            if ($workspace->isDirty('name')) {
                $workspace->nav_version = $workspace->nav_version + 1;
            }
        });
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

    /**
     * Only the owner can open a business for now: staff are records without a
     * login. When staff can sign in, their access is decided here.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
