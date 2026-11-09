<?php

namespace App\Models\Billing;

use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's subscription to a paid plan (Doc 13 sections 11 and 12): the
 * plan, monthly or yearly, and where it stands - on a trial, awaiting its
 * first payment, or active for a paid period. One per business, reused when
 * it subscribes again; its trial, once used, stays recorded.
 *
 * A business without one is on Billing Starter - or on a plan izSuite set it
 * up with in the admin.
 */
class WorkspaceSubscription extends Model
{
    public const MONTHLY          = 'monthly';

    public const YEARLY           = 'yearly';

    public const CYCLES           = [self::MONTHLY, self::YEARLY];

    public const TRIALING         = 'trialing';

    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const ACTIVE           = 'active';

    /** Its paid period ended unpaid: the plan stays until grace_ends_on (Doc 13 Phase C2). */
    public const GRACE            = 'grace';

    public const CANCELLED        = 'cancelled';

    /** The statuses of a subscription the business is in - another cannot be started. */
    public const CURRENT          = [self::TRIALING, self::AWAITING_PAYMENT, self::ACTIVE, self::GRACE];

    /** The statuses the daily cycle moves on (SubscriptionCycle). */
    public const RUNNING          = [self::TRIALING, self::ACTIVE, self::GRACE];

    protected $fillable           = [
        'package_id',
        'cycle',
        'status',
        'trial_starts_on',
        'trial_ends_on',
        'period_starts_on',
        'period_ends_on',
        'grace_ends_on',
        'cancels_at_period_end',
        'scheduled_package_id',
        'scheduled_cycle',
        'last_notice_on',
    ];

    protected $casts              = [
        'trial_starts_on'       => 'date',
        'trial_ends_on'         => 'date',
        'period_starts_on'      => 'date',
        'period_ends_on'        => 'date',
        'grace_ends_on'         => 'date',
        'cancels_at_period_end' => 'boolean',
        'last_notice_on'        => 'date',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** The lower plan - or the same one, monthly - it moves to as its paid period ends (Phase C2b). */
    public function scheduledPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'scheduled_package_id');
    }

    public function isCurrent(): bool
    {
        return in_array($this->status, self::CURRENT, true);
    }
}
