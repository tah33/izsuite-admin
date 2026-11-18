<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use Illuminate\Support\Collection;

/**
 * Win Rate (Doc 17 section 17.29 - Phase H2): of the opportunities the user
 * sees won or lost in the range, by the month each closed in - every month
 * of the range, a quiet one too - owner, sales team or lead source: how many
 * were won and lost, the share won, their values in the base currency at
 * today's rates, and the share of the value won.
 */
class WinRateReport extends OpportunityReport
{
    private const BY = ['month', 'owner', 'team', 'source'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base   = $workspace->base_currency;
        $by     = in_array($filters['group_by'], self::BY, true) ? $filters['group_by'] : 'month';
        $rows   = $this->closed($workspace, $filters);
        $values = $this->forecast->baseValues($workspace, $rows);

        $figures = function (Collection $rows) use ($values) {
            $won       = $rows->where('status', CrmOpportunity::WON);
            $wonValue  = $this->sum($won, $values)['total'];
            $lostValue = $this->sum($rows->where('status', CrmOpportunity::LOST), $values)['total'];

            return [
                'closed'     => $rows->count(),
                'won'        => $won->count(),
                'lost'       => $rows->count() - $won->count(),
                'rate'       => $this->share($won->count(), $rows->count()),
                'won_value'  => $wonValue,
                'lost_value' => $lostValue,
                'value_rate' => $this->percent($wonValue, bcadd($wonValue, $lostValue, 2)),
            ];
        };

        return new ReportTable(
            title: $filters['report_name'] ?? 'Win Rate',
            period: $this->between($filters),
            columns: [
                $this->column('group', self::GROUPS[$by]),
                $this->column('closed', 'Closed', 'number'),
                $this->column('won', 'Won', 'number'),
                $this->column('lost', 'Lost', 'number'),
                $this->column('rate', 'Win Rate', 'percent'),
                $this->column('won_value', 'Won Value', 'money', $base),
                $this->column('lost_value', 'Lost Value', 'money', $base),
                $this->column('value_rate', 'Win Rate by Value', 'percent'),
            ],
            rows: array_map(fn (array $group) => ['group' => $group['label'], ...$figures($group['rows'])], $this->grouped($workspace, $rows, $by, $filters, fn (CrmOpportunity $row) => $row->closed_on->toDateString())),
            totals: ['group' => 'Total', ...$figures($rows)],
            notes: array_values(array_filter([
                $this->counting($filters, true).". The win rate is the share won of those closed; by value, the share of their value - in {$base} at today's rates - that was won.",
                $this->unratedNote($this->sum($rows, $values)['unrated']),
                self::NOT_SALES,
            ])),
        );
    }
}
