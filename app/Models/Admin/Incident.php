<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something wrong with a service (Doc 15 section 15.23 - Phase D3b), from
 * when it started until it is resolved: its severity, status, the admin
 * responsible, and a timeline of notes.
 */
class Incident extends Model
{
    public const OPEN          = 'open';

    public const INVESTIGATING = 'investigating';

    public const MONITORING    = 'monitoring';

    public const RESOLVED      = 'resolved';

    /** In the order an incident goes through them. */
    public const STATUSES      = [
        self::OPEN          => 'Open',
        self::INVESTIGATING => 'Investigating',
        self::MONITORING    => 'Monitoring',
        self::RESOLVED      => 'Resolved',
    ];

    /** Not resolved yet. */
    public const UNRESOLVED    = [self::OPEN, self::INVESTIGATING, self::MONITORING];

    public const SEVERITIES    = [
        'minor'    => 'Minor',
        'major'    => 'Major',
        'critical' => 'Critical',
    ];

    protected $fillable = [
        'title',
        'service',
        'severity',
        'status',
        'summary',
        'responsible_id',
        'started_at',
        'resolved_at',
        'created_by',
    ];

    protected $casts    = [
        'started_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function isResolved(): bool
    {
        return $this->status === self::RESOLVED;
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(IncidentNote::class);
    }
}
