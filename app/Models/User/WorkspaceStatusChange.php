<?php

namespace App\Models\User;

use App\Support\BusinessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a business's status (Doc 15 section 15.6): the status it
 * moved to, why (shown to the owner), what reactivates it, internal notes,
 * the day it takes effect or the closing day, whether the owner was told,
 * and the admin - none when izSuite closed it on the scheduled day.
 */
class WorkspaceStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'workspace_id',
        'status',
        'previous_status',
        'reason',
        'reactivation',
        'notes',
        'effective_on',
        'closes_on',
        'owner_notified',
        'user_id',
        'admin_name',
    ];

    protected function casts(): array
    {
        return [
            'effective_on'   => 'date',
            'closes_on'      => 'date',
            'owner_notified' => 'boolean',
            'created_at'     => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function label(): string
    {
        return BusinessStatus::label($this->status);
    }
}
