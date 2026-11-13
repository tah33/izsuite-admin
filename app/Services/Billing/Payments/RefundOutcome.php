<?php

namespace App\Services\Billing\Payments;

/**
 * A refund as the card provider has it (Doc 15 Phase C2a): its reference,
 * where it stands - done, waiting, or refused with why - the payment it
 * gives back, the amount in the currency's smallest unit, and izSuite's own
 * refund when izSuite asked for it (null for one made in the provider's
 * dashboard).
 */
final class RefundOutcome
{
    public const SUCCEEDED = 'succeeded';

    public const PENDING   = 'pending';

    public const FAILED    = 'failed';

    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly ?string $paymentRef,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ?int $refundId = null,
        public readonly ?string $note = null,
    ) {}
}
