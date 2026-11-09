<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * What the daily subscription cycle tells a business (Doc 13 sections 12
 * and 19): its trial ends in a few days; its paid period has ended and how
 * many days of grace are left to pay before it goes back to the free plan -
 * one a day; or its plan has ended. Queued once the transaction commits.
 *
 * $details: plan and business, and by kind - days_left, the trial's or the
 * grace's last day, the day it moves to the free plan, the invoice and its
 * total; for an end, why (trial, grace, cancelled, ended).
 */
class SubscriptionNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const TRIAL_ENDING = 'trial_ending';

    public const GRACE        = 'grace';

    public const ENDED        = 'ended';

    /** The saved card was not charged on the due day (Phase D3): declined, or the cardholder to confirm it. */
    public const PAYMENT_FAILED = 'payment_failed';

    public function __construct(
        public string $kind,
        public array $details,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $plan = $this->details['plan'];
        $days = $this->details['days_left'] ?? null;

        return new Envelope(subject: match ($this->kind) {
            self::TRIAL_ENDING => $days === 1 ? "Your {$plan} trial ends tomorrow" : "Your {$plan} trial ends in {$days} days",
            self::GRACE        => $days === 0
                ? "Last day to renew your {$plan} subscription"
                : "Your {$plan} subscription has ended: {$days} ".($days === 1 ? 'day' : 'days').' left to renew',
            self::PAYMENT_FAILED => ($this->details['confirm'] ?? false) ? "Please confirm your payment for {$plan}" : "We could not charge your card for {$plan}",
            default            => "Your {$plan} plan has ended",
        });
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-notice',
            with: [
                'kind'    => $this->kind,
                'details' => $this->details,
                'link'    => rtrim((string) config('app.frontend_url'), '/').'/settings?tab=subscription',
            ],
        );
    }
}
