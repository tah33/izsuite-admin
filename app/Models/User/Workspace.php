<?php

namespace App\Models\User;

use App\Models\Frontend\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'app_id',
        'user_id',
        'staff_id',
    ];

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'app_id');
    }

    /**
     * One of the account's own staff - a row of user_staff, not a staff-role
     * account in users.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(UserStaff::class, 'staff_id');
    }
}
