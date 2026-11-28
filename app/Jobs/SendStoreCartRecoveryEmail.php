<?php

namespace App\Jobs;

use App\Services\Api\Ecommerce\EcommerceCartRecovery;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends an abandoned cart's recovery e-mail to its customer (Doc 19 Phase
 * H3b), with the link back to the cart - which the job carries encrypted:
 * only its token's hash is kept. Tried three times, a few minutes apart.
 */
class SendStoreCartRecoveryEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds */
    public array $backoff = [60, 300];

    public function __construct(
        public int $cartId,
        public string $link,
    ) {}

    public function handle(EcommerceCartRecovery $recovery): void
    {
        $recovery->deliver($this->cartId, $this->link);
    }
}
