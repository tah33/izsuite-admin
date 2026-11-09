<?php

namespace App\Repositories\Billing;

use App\Models\Billing\SubscriptionPayment;

class SubscriptionPaymentRepository
{
    public function exists(string $provider, string $reference): bool
    {
        return SubscriptionPayment::where('provider', $provider)->where('reference', $reference)->exists();
    }

    public function create(array $data): SubscriptionPayment
    {
        return SubscriptionPayment::create($data);
    }
}
