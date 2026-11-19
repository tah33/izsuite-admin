<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A POS session's closing (Doc 18 sections 18.27 and 18.28): its Z report.
 * What the session took (`totals`, as PosSessionTotals gave it), the opening
 * float, the cash expected and the cash counted, the variance - counted less
 * expected - with its reason, the closing notes, who closed it when, and
 * (Phase B2a) why a supervisor closed someone else's session.
 *
 * Its figures are kept as they were made: never changed, never deleted. It
 * is only marked, once each (MARKS): approved, when its variance went beyond
 * the limit; reopened, with the reason, when its session opens again - its
 * next closing then makes a new one.
 */
class PosSessionClosing extends Model
{
    /** What may be written on it afterwards, once each. */
    public const MARKS = ['approved_by', 'approved_at', 'reopened_by', 'reopened_at', 'reopen_reason'];

    protected $fillable = [
        'opening_float',
        'expected_cash',
        'counted_cash',
        'variance',
        'variance_reason',
        'notes',
        'closer_reason',
        'needs_approval',
        'totals',
        'closed_by',
        'closed_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'opening_float'  => 'decimal:2',
        'expected_cash'  => 'decimal:2',
        'counted_cash'   => 'decimal:2',
        'variance'       => 'decimal:2',
        'needs_approval' => 'boolean',
        'totals'         => 'array',
        'closed_at'      => 'datetime',
        'approved_at'    => 'datetime',
        'reopened_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $closing) {
            $others    = array_diff(array_keys($closing->getDirty()), [...self::MARKS, 'updated_at']);
            $rewritten = array_filter(self::MARKS, fn (string $mark) => $closing->isDirty($mark) && $closing->getOriginal($mark) !== null);

            if ($others !== [] || $rewritten !== []) {
                throw new LogicException('A Z report is never changed: it is only marked approved, or reopened, once.');
            }
        });
        static::deleting(fn () => throw new LogicException('A Z report is never deleted.'));
    }

    /** Its session reopened after it: no longer the session's Z report. */
    public function isReopened(): bool
    {
        return $this->reopened_at !== null;
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
