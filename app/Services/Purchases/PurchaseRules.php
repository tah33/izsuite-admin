<?php

namespace App\Services\Purchases;

use App\Enums\PurchaseStatus;
use App\Models\User\Purchase;
use App\Support\Decimal;

/**
 * What a purchase can do, and how it reads (Doc 09 sections 9 and 10). A
 * draft is edited, recorded or deleted. A recorded purchase is locked; it is
 * voided only while nothing has paid it - payments are reversed first. A void
 * one stays as it is. Overdue is worked out on read, never stored: recorded,
 * past its due date, with something left to pay.
 */
class PurchaseRules
{
    public function overdue(Purchase $purchase, string $today): bool
    {
        return $purchase->status === PurchaseStatus::Recorded
            && $purchase->due_date
            && $purchase->due_date < $today
            && bccomp((string) $purchase->balance_due, '0', 2) > 0;
    }

    /** @return list<string> */
    public function actions(Purchase $purchase): array
    {
        return match ($purchase->status) {
            PurchaseStatus::Draft    => ['edit', 'record', 'delete'],
            PurchaseStatus::Recorded => $this->settled($purchase) ? [] : ['void'],
            PurchaseStatus::Void     => [],
        };
    }

    public function allows(Purchase $purchase, string $action): bool
    {
        return in_array($action, $this->actions($purchase), true);
    }

    /** Whether anything has paid it: a payment, or tax withheld from one. */
    private function settled(Purchase $purchase): bool
    {
        return ! Decimal::isZero(Decimal::sum([$purchase->amount_paid, $purchase->withholding_amount]));
    }
}
