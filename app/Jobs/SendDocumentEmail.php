<?php

namespace App\Jobs;

use App\Services\Documents\DocumentMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one document email - a sales document, a receipt or a reminder - with
 * its PDF (see DocumentMailer). Tried three times, a few minutes apart, as a
 * mail server or the PDF maker can be away for a moment; after that its row
 * says it failed.
 *
 * The body travels with the job: it is the text the business wrote, filled in
 * when they sent it.
 */
class SendDocumentEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds */
    public array $backoff = [60, 300];

    public function __construct(
        public int $emailId,
        public string $body,
    ) {}

    public function handle(DocumentMailer $documentMailer): void
    {
        $documentMailer->deliver($this->emailId, $this->body);
    }

    public function failed(?\Throwable $exception): void
    {
        app(DocumentMailer::class)->failed($this->emailId, $exception);
    }
}
