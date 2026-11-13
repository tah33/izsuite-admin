<?php

namespace App\Models\Admin;

use App\Models\User\User;
use App\Support\DeviceName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Something an admin did in the admin panel, or tried (Doc 15 section
 * 15.26): who, in which role, to what, with its previous and new values, why
 * and how it was approved, from where, and how it ended. Never changed or
 * deleted once written: the model refuses to.
 */
class AdminAuditLog extends Model
{
    public const UPDATED_AT = null;

    /** How an action ended. */
    public const RESULTS = [
        'succeeded' => 'Succeeded',
        'failed'    => 'Failed',
        'refused'   => 'Refused',
    ];

    protected $fillable = [
        'user_id',
        'admin_name',
        'role_name',
        'action',
        'description',
        'model_type',
        'model_id',
        'changes',
        'reason',
        'approval',
        'result',
        'properties',
        'ip_address',
        'user_agent',
        'session',
    ];

    protected function casts(): array
    {
        return [
            'changes'    => 'array',
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('An audit entry is never changed.'));
        static::deleting(fn () => throw new LogicException('An audit entry is never deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Package #3": what it was done to, if anything. */
    public function record(): ?string
    {
        return $this->model_type ? class_basename($this->model_type).($this->model_id ? " #{$this->model_id}" : '') : null;
    }

    public function browser(): string
    {
        return DeviceName::from($this->user_agent) ?? ($this->user_agent ? mb_strimwidth($this->user_agent, 0, 40, '…') : 'Unknown browser');
    }
}
