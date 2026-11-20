<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Settings → Point of Sale (Doc 18 Phase A1): the walk-in customer an order
 * is for when the cashier names none (section 18.11) - or none - and what a
 * receipt ends with: the return policy and a footer (section 18.19). And
 * (Phase B2a) the variance limit: a session's closing whose variance goes
 * beyond it, in the session's currency, waits for someone who approves
 * variances - null for none to. Phase B2b: whether its cash is counted by
 * notes and coins, and each currency's as the business set them.
 */
class PosSetting extends Model
{
    protected $fillable = [
        'walk_in_contact_id',
        'return_policy',
        'receipt_footer',
        'variance_limit',
        // Phase F1: above it, a cash movement waits for a manager's approval.
        'cash_movement_limit',
        'counts_denominations',
        'denominations',
        // Phase C3: the discount limit (a percentage) and the cash rounding step.
        'discount_limit',
        'cash_rounding',
        // Phase D1: how many hours a held order is kept.
        'hold_hours',
        // Phase I1: the minutes without use after which a register locks.
        'lock_minutes',
    ];

    protected $casts = [
        'walk_in_contact_id'   => 'integer',
        'variance_limit'       => 'decimal:2',
        'cash_movement_limit'  => 'decimal:2',
        'counts_denominations' => 'boolean',
        'denominations'        => 'array',
        'discount_limit'       => 'decimal:2',
        'cash_rounding'        => 'decimal:2',
        'hold_hours'           => 'integer',
        'lock_minutes'         => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The contact a walk-in sale is for. */
    public function walkInContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'walk_in_contact_id');
    }
}
