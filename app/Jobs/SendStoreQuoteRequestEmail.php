<?php

namespace App\Jobs;

use App\Services\Api\Ecommerce\StorefrontQuoteRequests;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a request for a quotation's e-mail to its customer, saying it
 * arrived (Doc 19 Phase H4a). Tried three times, a few minutes apart.
 */
class SendStoreQuoteRequestEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds */
    public array $backoff = [60, 300];

    public function __construct(
        public int $requestId,
    ) {}

    public function handle(StorefrontQuoteRequests $requests): void
    {
        $requests->deliver($this->requestId);
    }
}
