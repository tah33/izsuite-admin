<?php

namespace App\Services\Reports\Expenses;

use App\Models\User\Expense;
use App\Models\User\ExpenseLine;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Expenses by Category (Doc 07 section 15): recorded expenses of the period,
 * in the base currency at the rate each kept, grouped by expense category,
 * payee, location, month or currency - how many, what they came to with the
 * tax that cannot be claimed back, the tax that can while taxes apply
 * (section 16), what had paid them - tax withheld apart, while withholding
 * applies - and what was still owed at the end of the period, or today if it
 * ends later. Drafts and void expenses never count.
 *
 * By category - or for one category - the figures come from the lines, each
 * with its share of what paid the expense. A payee's row opens their
 * profile; a month's, location's or currency's the expenses behind it.
 */
class ExpensesByCategoryReport extends Report
{
    public const GROUPS = ['category' => 'Expense Category', 'payee' => 'Payee', 'location' => 'Location', 'month' => 'Month', 'currency' => 'Currency'];

    private const FIGURES = ['amount', 'tax', 'paid', 'withheld', 'outstanding'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $group    = array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'category';
        $asOf     = min($filters['to'], $this->period->today($workspace));
        $category = $filters['expense_category'] ?? null;
        $rows     = $group === 'month' ? $this->monthRows($filters) : [];

        foreach ($this->expenses->recorded($workspace, $filters['from'], $filters['to'], $filters, $asOf) as $expense) {
            if ($group !== 'category' && ! $category) {
                [$key, $label, $link] = $this->groupOf($expense, null, $group, $filters);

                $rows[$key] ??= $this->row($label, $link);
                $rows[$key]   = $this->tally($rows[$key], $expense->id, $this->expenseFigures($expense));

                continue;
            }

            foreach ($expense->lines as $line) {
                if ($category && $line->expense_category_id !== $category->id) {
                    continue;
                }

                [$key, $label, $link] = $this->groupOf($expense, $line, $group, $filters);

                $rows[$key] ??= $this->row($label, $link);
                $rows[$key]   = $this->tally($rows[$key], $expense->id, $this->lineFigures($expense, $line));
            }
        }

        if ($group !== 'month') {
            uasort($rows, fn (array $a, array $b) => bccomp($b['amount'], $a['amount'], 2) ?: strcmp($a['group'], $b['group']));
        }

        $count = count(array_unique(array_merge([], ...array_column($rows, 'seen'))));
        $rows  = array_values(array_map(fn (array $row) => array_diff_key($row, ['seen' => true]), $rows));
        $base  = $workspace->base_currency;

        return new ReportTable(
            title: 'Expenses by Category',
            period: $this->between($filters).($category ? " · {$category->name}" : ''),
            columns: array_values(array_filter([
                $this->column('group', self::GROUPS[$group]),
                $this->column('expenses', 'Expenses', 'number'),
                $this->column('amount', 'Expense Amount', 'money', $base),
                $this->taxesShown($workspace) ? $this->column('tax', 'Recoverable Tax', 'money', $base) : null,
                $this->column('paid', 'Total Paid', 'money', $base),
                $this->withholdingShown($workspace) ? $this->column('withheld', 'Tax Withheld', 'money', $base) : null,
                $this->column('outstanding', 'Outstanding Balance', 'money', $base),
            ])),
            rows: $rows,
            // An expense whose lines fall into two groups counts once in the total.
            totals: ['group' => 'Total', 'expenses' => $count, ...$this->totals($rows, self::FIGURES)],
            notes: array_values(array_filter([
                "In {$base}, at the rate each expense kept. Expense Amount is before tax, with any tax that cannot be claimed back. Paid and outstanding are as they stood on {$this->day($asOf)}, tax included; drafts and void expenses are left out.",
                $group === 'category' || $category ? 'An expense counts in each category its lines are in; what paid it is shared out by the lines\' totals.' : null,
            ])),
        );
    }

    /** An expense's figures in the base currency: its amount, the tax it can claim back, and what had paid it and it still owed. */
    private function expenseFigures(Expense $expense): array
    {
        $amount = $this->figures->baseExpenseAmount($expense);

        return [
            'amount'      => $amount,
            'tax'         => bcsub(Decimal::round(Decimal::of($expense->base_grand_total)), $amount, 2),
            'paid'        => $this->figures->base($expense->paid_by, $expense->exchange_rate),
            'withheld'    => $this->figures->base($expense->withheld_by, $expense->exchange_rate),
            'outstanding' => $this->figures->base($this->figures->expenseOwedAsOf($expense), $expense->exchange_rate),
        ];
    }

    /** A line's own amount and tax, and its part of what paid the expense and it still owed - by its share of the total. */
    private function lineFigures(Expense $expense, ExpenseLine $line): array
    {
        $own   = $this->figures->expenseLine($expense, $line);
        $whole = (string) $expense->grand_total;
        $share = fn (mixed $value) => Decimal::isZero($whole) ? '0' : bcdiv(bcmul(Decimal::of($value), $own['total'], 10), $whole, 10);

        return [
            'amount'      => $this->figures->base($own['amount'], $expense->exchange_rate),
            'tax'         => $this->figures->base($own['tax'], $expense->exchange_rate),
            'paid'        => $this->figures->base($share($expense->paid_by), $expense->exchange_rate),
            'withheld'    => $this->figures->base($share($expense->withheld_by), $expense->exchange_rate),
            'outstanding' => $this->figures->base($share($this->figures->expenseOwedAsOf($expense)), $expense->exchange_rate),
        ];
    }

    /**
     * Where an expense - or one of its lines, by category - falls. A category
     * goes by the one it is now, so a renamed category stays one row.
     *
     * @return array{0: string, 1: string, 2: ?string} the group's key, what the report calls it, and where its row leads
     */
    private function groupOf(Expense $expense, ?ExpenseLine $line, string $group, array $filters): array
    {
        if ($group === 'month') {
            $key = $this->period->bucket($expense->document_date, 'month');

            return [$key, $this->period->label($key, 'month'), null];
        }

        return match ($group) {
            'category' => [
                $line->expense_category_id ? "category:{$line->expense_category_id}" : 'name:'.mb_strtolower((string) $line->category_name),
                $line->category?->name ?? $line->category_name ?? 'No category',
                null,
            ],
            'payee'    => $expense->contact_id
                ? ["contact:{$expense->contact_id}", $this->figures->payeeName($expense), "/contacts/{$expense->contact_id}"]
                : ['payee:'.mb_strtolower((string) $expense->payee_name), $this->figures->payeeName($expense), null],
            'location' => [
                "location:{$expense->location_id}",
                $expense->location?->name ?? 'No location',
                $expense->location_id ? $this->expensesLink($filters['from'], $filters['to'], $filters, ['location_id' => $expense->location_id]) : null,
            ],
            default    => [$expense->currency, $expense->currency, $this->expensesLink($filters['from'], $filters['to'], $filters, ['currency' => $expense->currency])],
        };
    }

    /** Every month of the range - a quiet one still shows - each opening the expenses behind it. */
    private function monthRows(array $filters): array
    {
        $rows = [];

        foreach ($this->period->buckets($filters['from'], $filters['to'], 'month') as $key) {
            [$start, $end] = $this->period->bounds($key, 'month', $filters['from'], $filters['to']);
            $rows[$key]    = $this->row($this->period->label($key, 'month'), $this->expensesLink($start, $end, $filters));
        }

        return $rows;
    }

    /** The Documents page with the recorded expenses behind a row. */
    private function expensesLink(string $from, string $to, array $filters, array $narrower = []): string
    {
        return $this->documentsLink($from, $to, $filters, ['status' => 'recorded', 'source' => 'expenses', ...$narrower]);
    }

    private function row(string $label, ?string $link = null): array
    {
        return ['group' => $label, 'expenses' => 0, ...array_fill_keys(self::FIGURES, '0.00'), 'link' => $link, 'seen' => []];
    }

    /** An expense's figures - or a line's - add to the row, which counts each expense once. */
    private function tally(array $row, int $expenseId, array $figures): array
    {
        if (! in_array($expenseId, $row['seen'], true)) {
            $row['seen'][] = $expenseId;
            $row['expenses']++;
        }

        foreach (self::FIGURES as $key) {
            $row[$key] = $this->add($row[$key], $figures[$key]);
        }

        return $row;
    }
}
