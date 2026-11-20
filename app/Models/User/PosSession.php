<?php

namespace App\Models\User;

use App\Services\User\Workspaces\WorkspaceSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A POS session (Doc 18 section 18.6 - Phase B1): one cashier's shift at one
 * register, from the opening cash float - a float, not income - to the
 * closing count. Numbered S-00001 in the business, in the register's
 * currency as it opened. Open, then Closed with its Z report (a
 * PosSessionClosing) - or, when its variance went beyond the limit, Closing
 * until someone who approves variances approves it (Phase B2a). Reopened,
 * it is Open again and its next closing makes a new Z report. Suspended
 * waits for Phase I. A session is never deleted.
 */
class PosSession extends Model
{
    public const OPEN = 'open';

    public const CLOSING = 'closing';

    public const CLOSED = 'closed';

    public const SUSPENDED = 'suspended';

    public const STATUSES = [self::OPEN, self::CLOSING, self::CLOSED, self::SUSPENDED];

    /**
     * Still selling, or able to: its register and its cashier are taken. A
     * session Closing has been counted, and waits for its variance's
     * approval only, so the next one may open.
     */
    public const LIVE = [self::OPEN, self::SUSPENDED];

    /** Counted: a Z report says what it took. */
    public const COUNTED = [self::CLOSING, self::CLOSED];

    public const NUMBER_SEQUENCE = 'pos_session';

    protected $fillable = [
        'pos_register_id',
        'cashier_id',
        'currency',
        'status',
        'opened_at',
        'opening_float',
        'opening_notes',
        'closed_at',
        'closed_by',
        // Phase I1: when it was last used, and whether its register is locked.
        'last_active_at',
        'locked_at',
    ];

    protected $casts = [
        'pos_register_id' => 'integer',
        'cashier_id'      => 'integer',
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
        'opening_float'   => 'decimal:2',
        'last_active_at'  => 'datetime',
        'locked_at'       => 'datetime',
    ];

    protected $attributes = [
        'status' => self::OPEN,
    ];

    public static function formatNumber(int $number): string
    {
        return sprintf('S-%05d', $number);
    }

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            $session->number ??= self::formatNumber(app(WorkspaceSequence::class)->next($session->workspace_id, self::NUMBER_SEQUENCE));
        });
        static::deleting(fn () => throw new \LogicException('A POS session is never deleted.'));
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE, true);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'pos_register_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Each time it was closed, the first first. */
    public function closings(): HasMany
    {
        return $this->hasMany(PosSessionClosing::class)->orderBy('id');
    }

    /** Its Z report: the last closing. */
    public function closing(): HasOne
    {
        return $this->hasOne(PosSessionClosing::class)->latestOfMany();
    }
}
