<?php

namespace App\Services\Api\Expenses;

use App\Models\User\Expense;
use App\Repositories\User\ExpensePaymentRepository;
use App\Repositories\User\ExpenseRepository;
use App\Support\Decimal;

/**
 * What a recorded expense has been paid and still owes (Doc 10 sections 6 and
 * 12), worked out again from its completed payments whenever one is made or
 * reversed: amount_paid is what the payments paid off it, withholding_amount
 * the tax withheld from the payee, and balance_due the rest - never below
 * zero. Its payment status follows: paid, partially paid or unpaid.
 */
class ExpenseSettlement
{
    public function __construct(
        protected ExpenseRepository $expenseRepository,
        protected ExpensePaymentRepository $expensePaymentRepository,
    ) {}

    public function settle(Expense $expense): void
    {
        $payments = $this->expensePaymentRepository->completedFor($expense);
        $paid     = Decimal::sum($payments->pluck('applied_amount'));
        $withheld = Decimal::sum($payments->pluck('withholding_amount'));
        $left     = bcsub(bcsub((string) $expense->grand_total, $paid, 2), $withheld, 2);
        $balance  = bccomp($left, '0', 2) > 0 ? $left : '0.00';

        $this->expenseRepository->update($expense, [
            'amount_paid'        => $paid,
            'withholding_amount' => $withheld,
            'balance_due'        => $balance,
            'payment_status'     => match (true) {
                bccomp($balance, '0', 2) === 0                    => Expense::PAYMENT_PAID,
                ! Decimal::isZero(Decimal::sum([$paid, $withheld])) => Expense::PAYMENT_PARTIALLY_PAID,
                default                                           => Expense::PAYMENT_UNPAID,
            },
        ]);
    }
}
