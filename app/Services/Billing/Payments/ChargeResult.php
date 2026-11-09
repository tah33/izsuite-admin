<?php

namespace App\Services\Billing\Payments;

/**
 * What charging a saved card without the business there came to (Doc 13
 * Phase D3): taken; refused by the bank, with its reason; the bank asking
 * the cardholder to confirm it; or still processing. The provider's
 * payment reference when it made one, and - taken - the amount in the
 * currency's smallest unit.
 */
final class ChargeResult
{
    public const SUCCEEDED       = 'succeeded';

    public const FAILED          = 'failed';

    public const ACTION_REQUIRED = 'action_required';

    public const PROCESSING      = 'processing';

    public function __construct(
        public readonly string $status,
        public readonly ?string $paymentRef,
        public readonly ?string $message = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
    ) {}
}
