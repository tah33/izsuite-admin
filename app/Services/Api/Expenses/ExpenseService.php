<?php

namespace App\Services\Api\Expenses;

use App\Enums\ExpenseStatus;
use App\Models\User\Expense;
use App\Models\User\ExpenseLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\ExpenseRepository;
use App\Repositories\User\ExpenseSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesLines;
use App\Services\Expenses\ExpenseRules;
use App\Services\Sales\SalesCalculator;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Support\Csv;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expenses Lite's expenses (Doc 10): the list, one expense, a draft added,
 * changed or deleted, and the list as CSV. Recording and voiding are
 * ExpenseFlow's.
 *
 * Every save works the totals out again from the lines with SalesCalculator -
 * the same arithmetic as a sale or a purchase, without discounts: what a form
 * shows is never taken as a total (section 6). An expense takes the business's
 * tax settings when it is made and keeps them. A recorded expense cannot be
 * changed (section 10).
 */
class ExpenseService
{
    use RefusesConflicts;

    private const TOTALS = ['subtotal', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total', 'base_net_total', 'taxes'];

    public function __construct(
        protected ExpenseRepository $expenseRepository,
        protected ExpenseSettingRepository $expenseSettingRepository,
        protected ContactRepository $contactRepository,
        protected ExpenseLines $expenseLines,
        protected SalesLines $salesLines,
        protected SalesCalculator $salesCalculator,
        protected ExpenseRules $expenseRules,
        protected BusinessCalendar $businessCalendar,
    ) {}

    /** The business's today, which overdue is measured by: "2026-10-28". */
    public function today(Workspace $workspace): string
    {
        return $this->businessCalendar->today($workspace)->toDateString();
    }

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->expenseRepository->paginateFor($workspace, $filters, $perPage, $this->today($workspace));
    }

    /** Another business's expense answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): Expense
    {
        return $this->expenseRepository->findFor($workspace, $id) ?? abort(404, 'Expense not found.');
    }

    /** $data is validated (see SaveExpenseRequest). */
    public function create(Workspace $workspace, User $user, array $data): Expense
    {
        $expense = DB::transaction(function () use ($workspace, $user, $data) {
            $header = [
                ...$this->header($workspace, $data),
                'price_tax_mode' => $workspace->price_tax_mode,
                'tax_rounding'   => $workspace->tax_rounding,
                'created_by'     => $user->id,
                'updated_by'     => $user->id,
            ];

            [$totals, $rows] = $this->totals($header, $this->expenseLines->rows($workspace, $data['lines']));

            $expense = $this->expenseRepository->create($workspace, [...$header, ...$totals]);
            $this->expenseRepository->replaceLines($expense, $rows);

            return $expense;
        });

        $expense = $this->expenseRepository->loadShown($expense);

        // Each write is logged here, by what it is, so LogApiWriteActivity
        // does not add its own vaguer entry.
        ActivityLogService::record('created', 'Added '.$this->describe($expense), $expense, ['source' => 'api']);

        return $expense;
    }

    public function update(Workspace $workspace, int $id, User $user, array $data): Expense
    {
        $expense = $this->find($workspace, $id);

        if (! $this->expenseRules->allows($expense, 'edit')) {
            $this->refuse('A recorded expense cannot be changed. Void it and record it again if it is wrong.');
        }

        DB::transaction(function () use ($workspace, $user, $data, $expense) {
            $header = [...$this->header($workspace, $data), 'updated_by' => $user->id];

            [$totals, $rows] = $this->totals([...$header, ...$expense->only(['price_tax_mode', 'tax_rounding'])], $this->expenseLines->rows($workspace, $data['lines']));

            $this->expenseRepository->update($expense, [...$header, ...$totals]);
            $this->expenseRepository->replaceLines($expense, $rows);
        });

        ActivityLogService::record('updated', 'Updated '.$this->describe($expense), $expense, ['source' => 'api']);

        return $this->expenseRepository->loadShown($expense);
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $expense = $this->find($workspace, $id);

        if (! $this->expenseRules->allows($expense, 'delete')) {
            $this->refuse('Only a draft can be deleted. Void a recorded expense instead.');
        }

        $this->expenseRepository->delete($expense);

        ActivityLogService::record('deleted', 'Deleted '.$this->describe($expense), $expense, ['source' => 'api']);
    }

    /**
     * The totals of an expense with $header's tax settings and exchange rate,
     * and its line rows with their amounts filled in. Expenses take no
     * discounts (Doc 10 section 6).
     *
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    public function totals(array $header, array $rows): array
    {
        $result = $this->salesCalculator->calculate(
            array_map(fn (array $row) => $this->salesLines->forCalculation($row), $rows),
            null,
            null,
            $header['price_tax_mode'],
            $header['tax_rounding'],
            (string) $header['exchange_rate'],
        );

        foreach ($rows as $key => $row) {
            $rows[$key] = [...$row, ...Arr::only($result['lines'][$key], ['amount', 'tax_amount'])];
        }

        return [Arr::only($result, self::TOTALS), $rows];
    }

    /** The list as the page shows it, whole, as CSV (section 10: Export) - logged. */
    public function export(Workspace $workspace, array $filters): StreamedResponse
    {
        $today = $this->today($workspace);
        $rows  = $this->expenseRepository->lazyFor($workspace, $filters, $today)->map(fn (Expense $expense) => [
            $expense->document_date,
            $expense->number ?? 'Draft',
            $expense->receipt_number,
            $expense->payeeName(),
            $this->categories($expense),
            $expense->status->label(),
            $expense->status === ExpenseStatus::Recorded ? Str::headline($expense->payment_status) : '',
            $this->expenseRules->overdue($expense, $today) ? 'Overdue' : '',
            $expense->due_date,
            $expense->currency,
            $expense->grand_total,
            $expense->amount_paid,
            $expense->balance_due,
            $expense->location?->name,
        ]);

        ActivityLogService::record('exported', 'Exported expenses', $workspace, ['source' => 'api']);

        return Csv::download("expenses-{$today}.csv", [
            'Date', 'Number', 'Receipt Number', 'Payee', 'Categories', 'Status', 'Payment Status', 'Overdue', 'Due Date', 'Currency', 'Total', 'Amount Paid', 'Balance Due', 'Location',
        ], $rows);
    }

    /** What the log calls it: "expense EXP-00001", or "draft expense to "City Power"". */
    public function describe(Expense $expense): string
    {
        return $expense->number
            ? "expense {$expense->number}"
            : 'draft expense to "'.Str::limit($expense->payeeName() ?? 'a payee', 100).'"';
    }

    /** Its lines' categories, each once: "Rent; Utilities". */
    private function categories(Expense $expense): string
    {
        return $expense->lines->map(fn (ExpenseLine $line) => $line->category_name)->filter()->unique()->implode('; ');
    }

    /**
     * The header fields as kept. The payee is a contact or a name typed in,
     * never both. The due date is the form's, or its date plus the payment
     * terms - the form's, the payee contact's or the business's (Doc 10
     * section 5). An expense in the base currency has an exchange rate of 1.
     */
    private function header(Workspace $workspace, array $data): array
    {
        $terms   = $this->paymentTerms($workspace, $data);
        $contact = $data['contact_id'] ?? null;

        return [
            'contact_id'     => $contact,
            'payee_name'     => $contact ? null : $data['payee_name'],
            'location_id'    => $data['location_id'] ?? null,
            'document_date'  => $data['document_date'],
            'due_date'       => $data['due_date'] ?? CarbonImmutable::parse($data['document_date'])->addDays($terms)->toDateString(),
            'payment_terms'  => $terms,
            'currency'       => $data['currency'],
            'exchange_rate'  => $data['currency'] === $workspace->base_currency ? '1' : (string) $data['exchange_rate'],
            'receipt_number' => $data['receipt_number'] ?? null,
            'reference'      => $data['reference'] ?? null,
            'notes'          => $data['notes'] ?? null,
        ];
    }

    private function paymentTerms(Workspace $workspace, array $data): int
    {
        $contact = isset($data['contact_id']) ? $this->contactRepository->findFor($workspace, $data['contact_id']) : null;

        return (int) ($data['payment_terms']
            ?? $contact?->supplier_payment_terms
            ?? $this->expenseSettingRepository->forWorkspace($workspace)->payment_terms);
    }
}
