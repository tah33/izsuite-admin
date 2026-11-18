<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use Illuminate\Support\Collection;

/**
 * Opportunities by Stage (Doc 17 section 17.29 - Phase H2): the open
 * opportunities the user sees now at each open stage - every one switched
 * on, a quiet one too, in its pipeline's order - with their expected and
 * weighted value in the base currency at today's rates, their average days
 * at the stage so far, and how many are stale: longer at it than its stale
 * period (Phase D3a).
 */
class OpportunitiesByStageReport extends OpportunityReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base    = $workspace->base_currency;
        $open    = $this->open($workspace, $filters);
        $values  = $this->forecast->baseValues($workspace, $open);
        $now     = now();
        $figures = fn (Collection $rows) => [
            'count'    => $rows->count(),
            'value'    => $this->sum($rows, $values)['total'],
            'weighted' => $this->sum($rows, $values, 'weighted')['total'],
            'days'     => $this->averageDays($rows->pluck('stage_entered_at'), $now),
            'stale'    => $rows->filter(fn (CrmOpportunity $row) => $row->stage?->stale_after_days !== null && $row->stage_entered_at
                && $now->getTimestamp() - $row->stage_entered_at->getTimestamp() > $row->stage->stale_after_days * 86400)->count(),
        ];

        return new ReportTable(
            title: $filters['report_name'] ?? 'Opportunities by Stage',
            period: 'As of '.$this->day($this->period->today($workspace), $filters),
            columns: [
                $this->column('group', 'Stage'),
                $this->column('count', 'Opportunities', 'number'),
                $this->column('value', 'Expected Value', 'money', $base),
                $this->column('weighted', 'Weighted Value', 'money', $base),
                $this->column('days', 'Average Days at Stage', 'number'),
                $this->column('stale', 'Stale', 'number'),
            ],
            rows: array_map(fn (array $group) => ['group' => $group['label'], ...$figures($group['rows'])], $this->grouped($workspace, $open, 'stage', $filters)),
            totals: ['group' => 'Total', ...$figures($open)],
            notes: array_values(array_filter([
                $this->counting($filters, false).", at each open stage: their expected value and weighted value - expected value × probability - in {$base} at today's rates, their average days at the stage so far, and how many are stale, at it longer than its stale period.",
                $this->unratedNote($this->sum($open, $values)['unrated']),
                self::NOT_SALES,
            ])),
            chart: ['label' => 'group', 'value' => 'value'],
        );
    }
}
