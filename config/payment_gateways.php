<?php

use App\Services\Billing\Payments\StripeGateway;

/*
 * The online gateways izSuite takes card payments through (Doc 13 Phase D2),
 * by the payment method's slug. Others are added here as they are built.
 */
return [
    'drivers' => [
        'stripe' => StripeGateway::class,
    ],
];
