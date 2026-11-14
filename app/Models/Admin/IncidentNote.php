<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note on an incident's timeline (Doc 15 Phase D3b), by an admin - with
 * the status it moved the incident from and to, and who it made
 * responsible, by name as they were then.
 */
class IncidentNote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'incident_id',
        'user_id',
        'body',
        'status_from',
        'status_to',
        'responsible_to',
    ];

    protected $casts    = [
        'created_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
