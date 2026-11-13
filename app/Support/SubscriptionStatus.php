<?php

namespace App\Support;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\WorkspaceSubscription;

/**
 * A business's subscription as Admin > Subscriptions names it (Doc 15
 * section 15.13): its status, with an active one waiting to end or to move
 * down told apart. A business without one running is on Billing Starter,
 * Free, and is found in Admin > Businesses; one whose business is suspended
 * shows that beside it.
 */
class SubscriptionStatus
{
    public const CANCELLATION_SCHEDULED = 'cancellation_scheduled';

    public const PAST_DUE               = 'past_due';

    public const DOWNGRADE_SCHEDULED    = 'downgrade_scheduled';

    /** A card try that did not go through: refused, or waiting for the cardholder. */
    public const FAILED_TRIES           = [SubscriptionPayment::FAILED, SubscriptionPayment::ACTION_REQUIRED];

    public const LABELS = [
        WorkspaceSubscription::TRIALING         => 'Trial',
        WorkspaceSubscription::AWAITING_PAYMENT => 'Awaiting Payment',
        WorkspaceSubscription::ACTIVE           => 'Active',
        self::PAST_DUE                          => 'Past Due',
        self::CANCELLATION_SCHEDULED            => 'Cancellation Scheduled',
        self::DOWNGRADE_SCHEDULED               => 'Downgrade Scheduled',
        WorkspaceSubscription::GRACE            => 'Grace Period',
        WorkspaceSubscription::CANCELLED        => 'Cancelled',
    ];

    /**
     * Past Due as the app says it: the renewal's card refused while the paid
     * period still runs - read from SubscriptionAdminRepository's
     * open_invoice_kind and last_try_status.
     */
    public static function of(WorkspaceSubscription $subscription): string
    {
        if ($subscription->status === WorkspaceSubscription::ACTIVE && $subscription->cancels_at_period_end) {
            return self::CANCELLATION_SCHEDULED;
        }

        if ($subscription->status === WorkspaceSubscription::ACTIVE && $subscription->open_invoice_kind === SubscriptionInvoice::RENEWAL
            && in_array($subscription->last_try_status, self::FAILED_TRIES, true)) {
            return self::PAST_DUE;
        }

        if ($subscription->status === WorkspaceSubscription::ACTIVE && $subscription->scheduled_package_id) {
            return self::DOWNGRADE_SCHEDULED;
        }

        return $subscription->status;
    }

    public static function label(WorkspaceSubscription $subscription): string
    {
        return self::LABELS[self::of($subscription)] ?? ucfirst((string) $subscription->status);
    }
}
