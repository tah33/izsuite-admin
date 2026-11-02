<?php

namespace App\Services\Api\Expenses;

use App\Enums\DocumentType;
use App\Enums\ExpenseStatus;
use App\Models\User\Expense;
use App\Models\User\ExpenseLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseRepository;
use App\Repositories\User\ExpenseSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentTemplates;
use App\Services\Expenses\ExpenseRules;
use App\Services\Shared\ActivityLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An expense's status changes (Doc 10 sections 9 and 10), each allowed only
 * where ExpenseRules lists it:
 *
 * - record: a draft becomes recorded - numbered, its business, payee and
 *   template kept as they are now, its amounts locked, and its grand total
 *   owed to the payee until it is paid (Pay Later);
 * - void: a recorded expense is cancelled, while nothing has paid it.
 *
 * Before it is recorded, a payee contact must still be a supplier and each
 * line's tax still in effect on its date. A receipt that may have been
 * recorded before (section 11) - the same payee, and the same receipt number
 * or the same day, amount and currency - is recorded again only once
 * confirmed; an exact duplicate - all of them the same - only with a reason,
 * which it keeps. A missing receipt number never stops it being recorded.
 */
class ExpenseFlow
{
    use RefusesConflicts;

    public function __construct(
        protected ExpenseRepository $expenseRepository,
        protected ExpenseSettingRepository $expenseSettingRepository,
        protected ExpenseService $expenseService,
        protected ExpenseRules $expenseRules,
        protected SalesSnapshots $salesSnapshots,
        protected DocumentNumbers $documentNumbers,
        protected DocumentTemplates $documentTemplates,
    ) {}

    /** $duplicate is validated (see RecordExpenseRequest): confirmed, and a reason. */
    public function record(Workspace $workspace, int $id, User $user, array $duplicate = []): Expense
    {
        $expense = $this->allowed($workspace, $id, 'record', 'Only a draft can be recorded.');

        $this->checkReady($expense);
        $this->checkDuplicates($workspace, $expense, $duplicate);

        DB::transaction(fn () => $this->expenseRepository->update($expense, [
            'number'            => $this->documentNumbers->next(
                $workspace,
                DocumentType::Expense,
                CarbonImmutable::parse($expense->document_date),
                fn (string $number) => $this->expenseRepository->numberTaken($workspace, $number),
                $expense->location_id,
            ),
            'status'            => ExpenseStatus::Recorded,
            'payment_status'    => bccomp((string) $expense->grand_total, '0', 2) > 0 ? Expense::PAYMENT_UNPAID : Expense::PAYMENT_PAID,
            'balance_due'       => $expense->grand_total,
            'business_snapshot' => $this->salesSnapshots->business($workspace, $expense->location),
            'payee_snapshot'    => $expense->contact ? $this->salesSnapshots->customer($expense->contact) : ['name' => $expense->payee_name],
            'template_snapshot' => $this->documentTemplates->snapshot($workspace, DocumentType::Expense),
            'duplicate_reason'  => $duplicate['reason'] ?? null,
            'recorded_at'       => now(),
            'updated_by'        => $user->id,
        ]));

        return $this->logged($expense, 'Recorded '.$this->expenseService->describe($expense));
    }

    public function void(Workspace $workspace, int $id, User $user, ?string $reason): Expense
    {
        $expense = $this->allowed($workspace, $id, 'void', $this->expenseService->find($workspace, $id)->status === ExpenseStatus::Recorded
            ? 'Payments have settled some of this expense. Reverse them first.'
            : 'Only a recorded expense can be voided. A draft is deleted instead.');

        $this->expenseRepository->update($expense, [
            'status'         => ExpenseStatus::Void,
            'payment_status' => Expense::PAYMENT_NOT_APPLICABLE,
            'balance_due'    => '0',
            'status_reason'  => $reason,
            'updated_by'     => $user->id,
        ]);

        return $this->logged($expense, 'Voided '.$this->expenseService->describe($expense));
    }

    private function allowed(Workspace $workspace, int $id, string $action, string $refusal): Expense
    {
        $expense = $this->expenseService->find($workspace, $id);

        if (! $this->expenseRules->allows($expense, $action)) {
            $this->refuse($refusal);
        }

        return $expense;
    }

    private function checkReady(Expense $expense): void
    {
        if ($expense->contact_id && ! $expense->contact?->isSupplier()) {
            $this->refuse('Choose the payee again: this contact is not a supplier any more.');
        }

        if ($expense->lines->isEmpty()) {
            $this->refuse('Add at least one line first.');
        }

        $this->expenseRepository->loadLineReferences($expense);

        $stale = $expense->lines->first(fn (ExpenseLine $line) => $line->tax_rate_id && ! $this->taxInEffect($expense, $line));

        if ($stale) {
            $this->refuse("{$stale->tax_name} on the {$stale->category_name} line is no longer in effect on {$expense->document_date}. Edit it and choose the current rate first.");
        }
    }

    private function taxInEffect(Expense $expense, ExpenseLine $line): bool
    {
        $rate = $line->taxRate;

        return $rate && $rate->is_active && $rate->effective_from <= $expense->document_date
            && (! $rate->effective_to || $rate->effective_to >= $expense->document_date);
    }

    /**
     * A receipt that may have been recorded before - when Settings > Expenses
     * checks for one - is refused (a validation error, naming the earlier
     * expense) until the duplicate is confirmed, or, for an exact one, given a
     * reason. Two receipts with different numbers are never duplicates.
     */
    private function checkDuplicates(Workspace $workspace, Expense $expense, array $duplicate): void
    {
        if (! $this->expenseSettingRepository->forWorkspace($workspace)->duplicate_check) {
            return;
        }

        $receipt = $expense->receipt_number;
        $same    = fn (Expense $other) => $other->document_date === $expense->document_date
            && $other->currency === $expense->currency
            && bccomp((string) $other->grand_total, (string) $expense->grand_total, 2) === 0;

        $earlier = $this->expenseRepository->duplicatesOf($expense)->filter(fn (Expense $other) => ($receipt && $other->receipt_number === $receipt)
            || ((! $receipt || ! $other->receipt_number) && $same($other)))->values();
        $exact = $receipt ? $earlier->first(fn (Expense $other) => $other->receipt_number === $receipt && $same($other)) : null;

        if ($exact && empty($duplicate['reason'])) {
            throw ValidationException::withMessages([
                'duplicate_reason' => "{$exact->number} has the same receipt from this payee - number {$receipt}, its date, amount and currency. Give a reason to record this one too.",
            ]);
        }

        if (! $exact && $earlier->isNotEmpty() && empty($duplicate['confirmed']) && empty($duplicate['reason'])) {
            throw ValidationException::withMessages([
                'duplicate' => $receipt && $earlier->contains(fn (Expense $other) => $other->receipt_number === $receipt)
                    ? $this->numbers($earlier)." already has receipt number {$receipt} from this payee. Record this one anyway?"
                    : $this->numbers($earlier).($earlier->count() > 1 ? ' were' : ' was').' recorded for this payee on the same day, for the same amount. Record this one anyway?',
            ]);
        }
    }

    /** "EXP-00003" or "EXP-00003 and EXP-00007". */
    private function numbers(Collection $expenses): string
    {
        $numbers = $expenses->pluck('number')->all();

        return count($numbers) > 1 ? implode(', ', array_slice($numbers, 0, -1)).' and '.end($numbers) : $numbers[0];
    }

    private function logged(Expense $expense, string $description): Expense
    {
        // Logged here, by what happened, so LogApiWriteActivity does not add
        // its own vaguer entry.
        ActivityLogService::record('updated', $description, $expense, ['source' => 'api', 'status' => $expense->status->value]);

        return $this->expenseRepository->loadShown($expense);
    }
}
