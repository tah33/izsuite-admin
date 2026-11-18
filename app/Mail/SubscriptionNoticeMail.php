<?php

namespace App\Mail;

use App\Mail\Concerns\UsesEmailTemplate;
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
    use Queueable, SerializesModels, UsesEmailTemplate;

    public const TRIAL_ENDING = 'trial_ending';

    public const GRACE        = 'grace';

    public const ENDED        = 'ended';

    /** The saved card was not charged on the due day (Phase D3): declined, or the cardholder to confirm it. */
    public const PAYMENT_FAILED = 'payment_failed';

    /** izSuite gave money back (Doc 15 Phase C2a): amount, method and, when it had one, the invoice. */
    public const REFUNDED       = 'refunded';

    /** A new price for the plan, from the first renewal on or after a day well ahead (Doc 15 Phase C3a): was, will be, from, cycle. */
    public const PRICE_CHANGE   = 'price_change';

    public function __construct(
        public string $kind,
        public array $details,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $plan = $this->details['plan'] ?? null;
        $days = $this->details['days_left'] ?? null;

        return new Envelope(subject: $this->templated()['subject'] ?? match ($this->kind) {
            self::TRIAL_ENDING => $days === 1 ? "Your {$plan} trial ends tomorrow" : "Your {$plan} trial ends in {$days} days",
            self::GRACE        => $days === 0
                ? "Last day to renew your {$plan} subscription"
                : "Your {$plan} subscription has ended: {$days} ".($days === 1 ? 'day' : 'days').' left to renew',
            self::PAYMENT_FAILED => ($this->details['confirm'] ?? false) ? "Please confirm your payment for {$plan}" : "We could not charge your card for {$plan}",
            self::REFUNDED       => "izSuite has refunded {$this->details['amount']}",
            self::PRICE_CHANGE   => "Your {$plan} price changes from {$this->details['from']}",
            default            => "Your {$plan} plan has ended",
        });
    }

    /** In the admin's words when it wrote them (Doc 15 Phase F2), else these. */
    public function content(): Content
    {
        if ($words = $this->templated()) {
            return $this->templatedContent($words);
        }

        return new Content(
            view: 'emails.subscription-notice',
            with: [
                'kind'    => $this->kind,
                'details' => $this->details,
                'link'    => $this->link(),
            ],
        );
    }

    protected function templateKey(): string
    {
        return "subscription.{$this->kind}";
    }

    protected function templateVariables(): array
    {
        return [...$this->details, 'link' => $this->link()];
    }

    private function link(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/settings?tab=subscription';
    }
}
