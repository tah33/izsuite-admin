<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message on a support case. One izSuite staff wrote (by_staff) is the
 * admin editor's markup; anyone else's is plain text, and is only ever shown
 * as text.
 */
class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'by_staff',
        'message',
        'attachment_path',
    ];

    protected $casts    = [
        'attachment_path' => 'array',
        'by_staff'        => 'boolean',
    ];

    /** A new message is the case's latest activity. */
    protected $touches  = ['ticket'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
