<?php

namespace App\Services\Reports\Sales;

use App\Models\User\SalesReturn;
use App\Models\User\SalesReturnLine;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The Returns Report (Doc 16 section 16.26 - Phase H3c): the returns of the
 * period that were submitted - not drafts or cancelled ones - by month,
 * item, customer, condition or resolution: how many, the quantity, and how
 * many are open (submitted or authorised), back (received or completed) or
 * rejected, as each stands now. By item, each line counts under its order
 * line's item, and a return once in each item it holds. No values: a return
 * has none.
 */
class ReturnsReport extends Report
{
    use GroupsSalesDocuments;

    public const GROUPS = ['month' => 'Month', 'item' => 'Item', 'customer' => 'Customer', 'condition' => 'Condition', 'resolution' => 'Resolution'];

    private const RESOLUTIONS = ['refund' => 'Refund', 'credit_note' => 'Credit Note', 'replacement' => 'Replacement', 'repair' => 'Repair', 'none' => 'No Financial Adjustment'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $group   = array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'month';
        $rows    = $group === 'month' ? $this->months($filters, fn (string $label) => $this->blank($label)) : [];
        $returns = $this->sales->returnsBetween($workspace, $filters['from'], $filters['to'], $filters);
        $seen    = [];

        foreach ($returns as $return) {
            foreach ($return->lines as $line) {
                [$key, $label] = $this->place($return, $line, $group, $filters);

                $rows[$key] ??= $this->blank($label);
                $rows[$key]['quantity'] = bcadd($rows[$key]['quantity'], Decimal::of($line->quantity), 3);

                if (! isset($seen[$key][$return->id])) {
                    $seen[$key][$return->id] = true;
                    $rows[$key]['returns']++;
                    $rows[$key][$this->outcome($return)]++;
                }
            }
        }

        if ($group !== 'month') {
            uasort($rows, fn (array $a, array $b) => $b['returns'] <=> $a['returns'] ?: strcmp($a['group'], $b['group']));
        }

        return new ReportTable(
            title: 'Returns Report',
            period: $this->between($filters),
            columns: [
                $this->column('group', self::GROUPS[$group]),
                $this->column('returns', 'Returns', 'number'),
                $this->column('quantity', 'Quantity', 'number'),
                $this->column('open', 'Open', 'number'),
                $this->column('received', 'Received', 'number'),
                $this->column('rejected', 'Rejected', 'number'),
            ],
            rows: array_values($rows),
            totals: $this->totalled($returns),
            notes: ['Returns submitted in the period, as each stands now: open while submitted or authorised, received once received or completed.'],
        );
    }

    /** Where a return line falls by $group, and what the report calls it. */
    private function place(SalesReturn $return, SalesReturnLine $line, string $group, array $filters): array
    {
        $ordered = $line->orderLine;

        return match ($group) {
            'month'     => $this->month($return->return_date, $filters),
            'item'      => $ordered?->item_id ? ["item:{$ordered->item_id}", $ordered->name] : ['line:'.mb_strtolower((string) $ordered?->name), $ordered->name ?? 'Unknown item'],
            'customer'  => ["contact:{$return->order?->contact_id}", $return->order ? $this->figures->customerName($return->order) : 'Unknown customer'],
            'condition' => ["condition:{$return->condition}", $return->condition ? Str::headline($return->condition) : 'Not recorded'],
            default     => ["resolution:{$return->resolution}", self::RESOLUTIONS[$return->resolution] ?? 'Not decided'],
        };
    }

    private function outcome(SalesReturn $return): string
    {
        return match (true) {
            in_array($return->status, SalesReturn::BACK, true) => 'received',
            $return->status === SalesReturn::REJECTED          => 'rejected',
            default                                            => 'open',
        };
    }

    /** The totals from the returns themselves: each once, whatever groups its lines fall into. */
    private function totalled(Collection $returns): array
    {
        $totals = ['group' => 'Total', 'returns' => $returns->count(), 'quantity' => '0', 'open' => 0, 'received' => 0, 'rejected' => 0];

        foreach ($returns as $return) {
            $totals[$this->outcome($return)]++;

            foreach ($return->lines as $line) {
                $totals['quantity'] = bcadd($totals['quantity'], Decimal::of($line->quantity), 3);
            }
        }

        return $totals;
    }

    private function blank(string $label): array
    {
        return ['group' => $label, 'returns' => 0, 'quantity' => '0', 'open' => 0, 'received' => 0, 'rejected' => 0];
    }
}
