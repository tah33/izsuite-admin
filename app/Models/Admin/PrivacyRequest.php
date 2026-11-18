<?php

namespace App\Models\Admin;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A privacy request (Doc 15 section 15.24 - Phase G1): a business's data
 * exported, an account closed or data corrected, as someone asked. Worked
 * only once the person asking is checked (verified); then completed, with
 * what was done, or rejected, with why.
 */
class PrivacyRequest extends Model
{
    public const EXPORT     = 'export';

    public const CLOSURE    = 'closure';

    public const CORRECTION = 'correction';

    public const TYPES = [
        self::EXPORT     => 'Data export',
        self::CLOSURE    => 'Account closure',
        self::CORRECTION => 'Data correction',
    ];

    public const RECEIVED    = 'received';

    public const VERIFIED    = 'verified';

    /** An export being built. */
    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED   = 'completed';

    public const REJECTED    = 'rejected';

    public const STATUSES = [
        self::RECEIVED    => 'Received',
        self::VERIFIED    => 'Identity checked',
        self::IN_PROGRESS => 'Export being prepared',
        self::COMPLETED   => 'Completed',
        self::REJECTED    => 'Rejected',
    ];

    /** How it reached izSuite. */
    public const CHANNELS = [
        'email'        => 'E-mail',
        'support_case' => 'Support case',
        'contact_form' => 'Contact form',
        'phone'        => 'Phone',
        'other'        => 'Other',
    ];

    /** Days to answer it in, from the day it came. */
    public const DUE_DAYS = 30;

    protected $fillable = [
        'type',
        'status',
        'user_id',
        'workspace_id',
        'requester_name',
        'requester_email',
        'received_via',
        'received_on',
        'due_on',
        'details',
        'identity_check',
        'verified_by',
        'verified_at',
        'outcome',
        'completed_by',
        'completed_at',
        'export_path',
        'export_size',
        'export_ready_at',
        'export_expires_at',
        'export_downloads',
        'export_downloaded_at',
        'created_by',
    ];

    protected $casts = [
        'received_on'          => 'date',
        'due_on'               => 'date',
        'verified_at'          => 'datetime',
        'completed_at'         => 'datetime',
        'export_size'          => 'integer',
        'export_ready_at'      => 'datetime',
        'export_expires_at'    => 'datetime',
        'export_downloads'     => 'integer',
        'export_downloaded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** As people quote it: PR-00012. */
    public function number(): string
    {
        return sprintf('PR-%05d', $this->id);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::RECEIVED, self::VERIFIED, self::IN_PROGRESS], true);
    }

    /** Still open after its due day. */
    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_on->lt(today());
    }

    /** Its export's file is there to download: made, and not expired. */
    public function exportAvailable(): bool
    {
        return $this->export_path !== null && $this->export_expires_at?->isFuture();
    }
}
