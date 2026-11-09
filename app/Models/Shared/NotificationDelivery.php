<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a notification reached its user (Doc 14 section 15 - Phase B1): in
 * the app, at once, or by e-mail through the queue - its status, tries and
 * the failure when it did not go.
 */
class NotificationDelivery extends Model
{
    public const IN_APP  = 'in_app';

    public const EMAIL   = 'email';

    public const PENDING = 'pending';

    public const SENT    = 'sent';

    public const FAILED  = 'failed';

    protected $fillable = [
        'user_notification_id',
        'channel',
        'status',
        'attempts',
        'failure',
        'sent_at',
    ];

    protected $casts    = [
        'attempts' => 'integer',
        'sent_at'  => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(UserNotification::class, 'user_notification_id');
    }
}
