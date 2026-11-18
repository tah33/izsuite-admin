<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmStageVisit;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Stage Duration (Doc 17 section 17.29 - Phase H2), from the stage visits
 * (Phase D3a): at each open stage, the visits of the opportunities the user
 * sees that ended in the range - moved to another stage, won or lost - with
 * their average and longest full days at it; and the open opportunities at
 * it now, with their average days so far.
 */
class StageDurationReport extends OpportunityReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $zone   = $this->calendar->timezone($workspace);
        $visits = $this->opportunities->stageVisitsEnded(
            $workspace,
            $this->visibility->scopeFor($workspace, $filters['user']),
            $this->narrowing($filters),
            CarbonImmutable::parse($filters['from'], $zone)->startOfDay()->utc(),
            CarbonImmutable::parse($filters['to'], $zone)->endOfDay()->utc(),
        );
        $open   = $this->open($workspace, $filters);
        $now    = now();
        $stages = $this->openStages($workspace, $filters);
        $shown  = array_column($stages, 'id');

        $figures = fn (Collection $ended, Collection $there) => [
            'moved'   => $ended->count(),
            'average' => $ended->isEmpty() ? null : Decimal::round(bcdiv((string) $ended->sum(fn (CrmStageVisit $visit) => $visit->seconds()), (string) ($ended->count() * 86400), 4), 1),
            'longest' => $ended->isEmpty() ? null : intdiv((int) $ended->max(fn (CrmStageVisit $visit) => $visit->seconds()), 86400),
            'there'   => $there->count(),
            'so_far'  => $this->averageDays($there->pluck('stage_entered_at'), $now),
        ];

        return new ReportTable(
            title: $filters['report_name'] ?? 'Stage Duration',
            period: $this->between($filters),
            columns: [
                $this->column('group', 'Stage'),
                $this->column('moved', 'Moved On', 'number'),
                $this->column('average', 'Average Days', 'number'),
                $this->column('longest', 'Longest Days', 'number'),
                $this->column('there', 'There Now', 'number'),
                $this->column('so_far', 'Average Days So Far', 'number'),
            ],
            rows: array_map(fn (array $stage) => ['group' => $stage['label'], ...$figures($visits->where('crm_stage_id', $stage['id']), $open->where('stage_id', $stage['id']))], $stages),
            totals: ['group' => 'Total', ...$figures($visits->whereIn('crm_stage_id', $shown), $open->whereIn('stage_id', $shown))],
            notes: [
                'At each open stage: the visits that ended from '.$this->day($filters['from'], $filters).' to '.$this->day($filters['to'], $filters).' - the opportunity moved to another stage, or was won or lost - with their average and longest full days there; and the open opportunities there now, with their average days so far.',
                'The opportunities you see, archived ones left out.',
                self::NOT_SALES,
            ],
        );
    }
}
