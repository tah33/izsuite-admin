<?php

namespace App\Services\Expenses;

use App\Enums\ExpenseStatus;
use App\Models\User\Expense;
use App\Support\Decimal;

/**
 * What an expense can do, and how it reads (Doc 10 sections 9 and 10). A
 * draft is edited, recorded or deleted. A recorded expense is locked: it
 * takes payments while something is left to pay, and is voided only while
 * nothing has paid it - payments are reversed first. A void one stays as it
 * is. Overdue is worked out on read, never stored: recorded,
 * past its due date, with something left to pay.
 */
class ExpenseRules
{
    public function overdue(Expense $expense, string $today): bool
    {
        return $expense->status === ExpenseStatus::Recorded
            && $expense->due_date
            && $expense->due_date < $today
            && bccomp((string) $expense->balance_due, '0', 2) > 0;
    }

    /** @return list<string> */
    public function actions(Expense $expense): array
    {
        return match ($expense->status) {
            ExpenseStatus::Draft    => ['edit', 'record', 'delete'],
            ExpenseStatus::Recorded => [
                ...(bccomp((string) $expense->balance_due, '0', 2) > 0 ? ['record_payment'] : []),
                ...($this->settled($expense) ? [] : ['void']),
            ],
            ExpenseStatus::Void     => [],
        };
    }

    public function allows(Expense $expense, string $action): bool
    {
        return in_array($action, $this->actions($expense), true);
    }

    /** Whether anything has paid it: a payment, or tax withheld from one. */
    private function settled(Expense $expense): bool
    {
        return ! Decimal::isZero(Decimal::sum([$expense->amount_paid, $expense->withholding_amount]));
    }
}
