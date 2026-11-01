<?php

namespace App\Services\Api\Purchases;

use App\Models\User\Purchase;
use App\Repositories\User\PurchaseRepository;
use App\Repositories\User\SupplierPaymentRepository;
use App\Support\Decimal;

/**
 * What a recorded purchase still owes its supplier (Doc 09 sections 9 and
 * 12), worked out again from scratch after every payment or reversal: its
 * completed payments come off - in cash and in tax withheld - and what is left
 * is its balance due, never below zero. Payment status follows: paid once
 * nothing is owed, partly paid once anything has paid it, else unpaid.
 */
class PurchaseSettlement
{
    public function __construct(
        protected PurchaseRepository $purchaseRepository,
        protected SupplierPaymentRepository $supplierPaymentRepository,
    ) {}

    public function settle(Purchase $purchase): void
    {
        $payments = $this->supplierPaymentRepository->completedFor($purchase);
        $paid     = Decimal::sum($payments->pluck('applied_amount'));
        $withheld = Decimal::sum($payments->pluck('withholding_amount'));
        $left     = bcsub(bcsub((string) $purchase->grand_total, $paid, 2), $withheld, 2);
        $balance  = bccomp($left, '0', 2) > 0 ? $left : '0.00';

        $this->purchaseRepository->update($purchase, [
            'amount_paid'        => $paid,
            'withholding_amount' => $withheld,
            'balance_due'        => $balance,
            'payment_status'     => match (true) {
                bccomp($balance, '0', 2) === 0                    => Purchase::PAYMENT_PAID,
                ! Decimal::isZero(Decimal::sum([$paid, $withheld])) => Purchase::PAYMENT_PARTIALLY_PAID,
                default                                           => Purchase::PAYMENT_UNPAID,
            },
        ]);
    }
}
