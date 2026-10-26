<?php

namespace App\Models\User;

use App\Models\Frontend\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a business's apps: turned on, replaced (Lite by Full), fallen
 * back (Full to Lite) or turned off.
 */
class WorkspaceAppLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIVATED = 'activated';

    public const REPLACED = 'replaced';

    public const FALLBACK = 'fallback';

    public const DEACTIVATED = 'deactivated';

    protected $fillable = [
        'workspace_id',
        'app_id',
        'from_app_id',
        'action',
        'user_id',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'app_id');
    }

    public function fromApp(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'from_app_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
