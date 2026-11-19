<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A POS session's closing (Doc 18 sections 18.27 and 18.28 - Phase B1): its
 * Z report. What the session took (`totals`, as PosSessionTotals gave it),
 * the opening float, the cash expected and the cash counted, the variance -
 * counted less expected - with its reason, the closing notes, and who closed
 * it when. Kept as it was made: never changed, never deleted.
 */
class PosSessionClosing extends Model
{
    protected $fillable = [
        'opening_float',
        'expected_cash',
        'counted_cash',
        'variance',
        'variance_reason',
        'notes',
        'totals',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'opening_float' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash'  => 'decimal:2',
        'variance'      => 'decimal:2',
        'totals'        => 'array',
        'closed_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A Z report is never changed.'));
        static::deleting(fn () => throw new LogicException('A Z report is never deleted.'));
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
