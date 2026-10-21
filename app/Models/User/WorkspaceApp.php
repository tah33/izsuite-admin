<?php

namespace App\Models\User;

use App\Models\Frontend\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's row for one app family: which edition is on, or that the family
 * was on before and is now off (its records stay readable).
 */
class WorkspaceApp extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'workspace_id',
        'family_key',
        'app_id',
        'status',
        'activated_by',
        'activated_at',
        'deactivated_at',
    ];

    protected $casts = [
        'activated_at'   => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'app_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
