<?php

namespace App\Jobs;

use App\Services\Api\Ecommerce\StoreOrderMailer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one e-mail of an online order to its customer (Doc 19 Phase D5) -
 * the order received, with its own page's link, or (Phase F2) confirmed, with
 * its invoice's secure link, or (Phase F3) its payment received or failed,
 * with the payment's details - which the job carries encrypted: only the
 * order link's hash is kept. Tried three times, a few minutes apart; after
 * that its row says it failed.
 */
class SendStoreOrderEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds */
    public array $backoff = [60, 300];

    /** $link: none for a payment failed before the order had an invoice. */
    public function __construct(
        public int $emailId,
        public ?string $link,
        public array $details = [],
    ) {}

    public function handle(StoreOrderMailer $mailer): void
    {
        $mailer->deliver($this->emailId, $this->link, $this->details);
    }

    public function failed(?\Throwable $exception): void
    {
        app(StoreOrderMailer::class)->failed($this->emailId, $exception);
    }
}
