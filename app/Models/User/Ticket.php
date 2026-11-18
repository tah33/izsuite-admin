<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A support case (Doc 15 section 15.22): asked by a user - for the business
 * it names, when opened from the app - and answered by izSuite staff.
 */
class Ticket extends Model
{
    use HasFactory;

    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const RESOLVED = 'resolved';

    public const CLOSED = 'closed';

    public const LABELS = [
        self::OPEN        => 'Open',
        self::IN_PROGRESS => 'In Progress',
        self::RESOLVED    => 'Resolved',
        self::CLOSED      => 'Closed',
    ];

    protected $fillable = [
        'user_id',
        'workspace_id',
        'assigned_to',
        'subject',
        'status',
        'priority',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(TicketMessage::class)->latestOfMany();
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? ucfirst((string) $this->status);
    }
}
