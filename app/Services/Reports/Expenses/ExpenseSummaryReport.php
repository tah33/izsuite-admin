<?php

namespace App\Services\Reports\Expenses;

use App\Models\User\Expense;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Expense Summary (Doc 07 section 14): each recorded expense of the period,
 * by its date - its categories, payee and what it was for as it kept them,
 * then its amount, the tax it can claim back shown apart, and its total, in
 * its own currency - with its amount in the base currency, which the totals
 * add up. Tax that cannot be claimed back stays in the amount (Doc 10
 * section 6). Drafts and void expenses never count. The tax column shows
 * while taxes apply (section 16).
 *
 * For one expense category, each expense shows only its lines in that
 * category. Each row opens its expense.
 */
class ExpenseSummaryReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base     = $workspace->base_currency;
        $taxed    = $this->taxesShown($workspace);
        $category = $filters['expense_category'] ?? null;
        $rows     = [];

        foreach ($this->expenses->recorded($workspace, $filters['from'], $filters['to'], $filters) as $expense) {
            $lines   = $category ? $expense->lines->where('expense_category_id', $category->id) : $expense->lines;
            $figures = $category ? $this->linesFigures($expense, $lines) : $this->figures($expense);

            $rows[] = [
                'expense_date'   => $expense->document_date,
                'number'         => $expense->number,
                'category'       => $lines->pluck('category_name')->filter()->unique()->implode(', '),
                'payee'          => $this->figures->payeeName($expense),
                'description'    => $lines->pluck('description')->filter()->unique()->implode('; '),
                'currency'       => $expense->currency,
                ...$figures,
                'payment_status' => Str::headline((string) $expense->payment_status),
                'location'       => $expense->location?->name,
                'link'           => "/expenses/{$expense->id}",
            ];
        }

        // The base tax and total only add up to the headline figures.
        $totals = $this->totals($rows, ['base_amount', 'base_tax', 'base_total']);
        $rows   = array_map(fn (array $row) => array_diff_key($row, ['base_tax' => true, 'base_total' => true]), $rows);

        return new ReportTable(
            title: 'Expense Summary',
            period: $this->between($filters).($category ? " · {$category->name}" : ''),
            columns: array_values(array_filter([
                $this->column('expense_date', 'Date', 'date'),
                $this->column('number', 'Expense'),
                $this->column('category', 'Category'),
                $this->column('payee', 'Payee'),
                $this->column('description', 'Description'),
                $this->column('currency', 'Currency'),
                $this->column('amount', 'Expense Amount', 'money'),
                $taxed ? $this->column('tax', 'Recoverable Tax', 'money') : null,
                $this->column('total', 'Total', 'money'),
                $this->column('base_amount', "Expense Amount ({$base})", 'money', $base),
                $this->column('payment_status', 'Payment Status'),
                $this->column('location', 'Location'),
            ])),
            rows: $rows,
            totals: ['expense_date' => 'Total', 'base_amount' => $totals['base_amount']],
            summary: array_values(array_filter([
                ['label' => 'Expense Amount', 'value' => $totals['base_amount'], 'currency' => $base],
                $taxed ? ['label' => 'Recoverable Tax', 'value' => $totals['base_tax'], 'currency' => $base] : null,
                ['label' => 'Total', 'value' => $totals['base_total'], 'currency' => $base],
            ])),
            notes: array_values(array_filter([
                $taxed
                    ? "Expense Amount is before tax, with any tax that cannot be claimed back; tax that can be is shown apart. Totals are in {$base}, at the rate each expense kept."
                    : "Totals are in {$base}, at the rate each expense kept.",
                'Drafts and void expenses are left out.',
                $category ? "Only each expense's {$category->name} lines count." : null,
            ])),
        );
    }

    /** An expense's own figures: its amount, the tax it can claim back and its total - in its currency, then the base one. */
    private function figures(Expense $expense): array
    {
        $amount = $this->figures->expenseAmount($expense);
        $base   = $this->figures->baseExpenseAmount($expense);
        $total  = Decimal::round(Decimal::of($expense->base_grand_total));

        return [
            'amount'      => $amount,
            'tax'         => bcsub((string) $expense->grand_total, $amount, 2),
            'total'       => (string) $expense->grand_total,
            'base_amount' => $base,
            'base_tax'    => bcsub($total, $base, 2),
            'base_total'  => $total,
        ];
    }

    /** The same for some of its lines - those of one category. */
    private function linesFigures(Expense $expense, Collection $lines): array
    {
        $shares = $lines->map(fn ($line) => $this->figures->expenseLine($expense, $line));
        $sum    = fn (string $key) => Decimal::sum($shares->pluck($key));

        return [
            'amount'      => $sum('amount'),
            'tax'         => $sum('tax'),
            'total'       => $sum('total'),
            'base_amount' => $this->figures->base($sum('amount'), $expense->exchange_rate),
            'base_tax'    => $this->figures->base($sum('tax'), $expense->exchange_rate),
            'base_total'  => $this->figures->base($sum('total'), $expense->exchange_rate),
        ];
    }
}
