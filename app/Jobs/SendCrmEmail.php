<?php

namespace App\Jobs;

use App\Jobs\Concerns\SafeToRetry;
use App\Services\Crm\CrmMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one e-mail from a lead, an opportunity or a contact (Doc 17 Phase
 * F3a), with what it carries (see CrmMailer). Tried three times, a few
 * minutes apart, as a mail server or the PDF maker can be away for a moment;
 * after that its row says it failed.
 */
class SendCrmEmail implements SafeToRetry, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds */
    public array $backoff = [60, 300];

    public function __construct(
        public int $emailId,
    ) {}

    public function handle(CrmMailer $crmMailer): void
    {
        $crmMailer->deliver($this->emailId);
    }

    public function failed(?\Throwable $exception): void
    {
        app(CrmMailer::class)->failed($this->emailId, $exception);
    }
}
