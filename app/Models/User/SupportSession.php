<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * izSuite support in a business (Doc 15 section 15.22): read only, or with
 * edit access the customer agreed to, for a support case, until it ends by
 * itself or is ended.
 */
class SupportSession extends Model
{
    public const READ_ONLY = 'read_only';

    public const EDIT = 'edit';

    public const ENDED_EXPIRED = 'expired';

    public const ENDED_BY_ADMIN = 'admin';

    public const ENDED_IN_APP = 'app';

    protected $fillable = [
        'workspace_id', 'ticket_id', 'user_id', 'admin_name', 'access', 'reason', 'customer_authorization',
        'started_at', 'expires_at', 'ended_at', 'end_reason', 'areas',
    ];

    protected $casts    = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at'   => 'datetime',
        'areas'      => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Open now: not ended, and before its end. */
    public function isLive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }

    public function canEdit(): bool
    {
        return $this->access === self::EDIT;
    }

    public function accessLabel(): string
    {
        return $this->canEdit() ? 'Edit access' : 'Read only';
    }

    /** The name its sessions' API tokens carry, which ties each token to it. */
    public function tokenName(): string
    {
        return 'izsuite-support:'.$this->id;
    }
}
