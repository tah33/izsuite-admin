<?php

namespace App\Models\User;

use App\Models\Billing\Package;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's usage on one day, in UTC (Doc 15 section 15.18 - Phase D2):
 * its users (active or invited), active locations, storage and active apps,
 * with its plan and that plan's limits then. One a day, by usage:snapshot.
 */
class WorkspaceUsageSnapshot extends Model
{
    protected $fillable = [
        'workspace_id',
        'day',
        'package_id',
        'users',
        'locations',
        'storage_bytes',
        'apps',
        'user_limit',
        'location_limit',
        'storage_limit_mb',
    ];

    protected $casts    = [
        'day'              => 'date',
        'users'            => 'integer',
        'locations'        => 'integer',
        'storage_bytes'    => 'integer',
        'apps'             => 'integer',
        'user_limit'       => 'integer',
        'location_limit'   => 'integer',
        'storage_limit_mb' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
