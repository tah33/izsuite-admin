<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use Illuminate\Support\Collection;

/**
 * Lost Reasons (Doc 17 sections 17.16 and 17.29 - Phase H2): the
 * opportunities the user sees lost in the range, by the reason each was
 * lost for - the most first - with each reason's share, their value in the
 * base currency at today's rates, and the competitor named most often.
 */
class LostReasonsReport extends OpportunityReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base    = $workspace->base_currency;
        $lost    = $this->closed($workspace, $filters)->where('status', CrmOpportunity::LOST)->values();
        $values  = $this->forecast->baseValues($workspace, $lost);
        $figures = fn (Collection $rows) => [
            'count' => $rows->count(),
            'share' => $this->share($rows->count(), $lost->count()),
            'value' => $this->sum($rows, $values)['total'],
        ];

        $groups = $lost->groupBy(fn (CrmOpportunity $row) => (string) ($row->lost_reason_id ?? 0))
            ->map(fn (Collection $rows) => [
                'group'      => $rows->first()->lostReason?->name ?? 'No reason',
                ...$figures($rows),
                // The competitor named most often, the first named of a tie.
                'competitor' => $rows->pluck('lost_competitor')->filter()->countBy()->sortDesc()->keys()->first(),
            ])
            ->sortBy(fn (array $row) => [-$row['count'], $row['group']])
            ->values()
            ->all();

        return new ReportTable(
            title: $filters['report_name'] ?? 'Lost Reasons',
            period: $this->between($filters),
            columns: [
                $this->column('group', 'Lost Reason'),
                $this->column('count', 'Opportunities', 'number'),
                $this->column('share', 'Share', 'percent'),
                $this->column('value', 'Lost Value', 'money', $base),
                $this->column('competitor', 'Main Competitor'),
            ],
            rows: $groups,
            totals: ['group' => 'Total', ...$figures($lost)],
            notes: array_values(array_filter([
                'The opportunities you see lost from '.$this->day($filters['from'], $filters).' to '.$this->day($filters['to'], $filters).", archived ones left out, by the reason each was lost for: its share, their value in {$base} at today's rates, and the competitor named most often.",
                $this->unratedNote($this->sum($lost, $values)['unrated']),
                self::NOT_SALES,
            ])),
            chart: ['label' => 'group', 'value' => 'value'],
        );
    }
}
