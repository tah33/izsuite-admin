<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmActivity;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Activity Completion (Doc 17 sections 17.17 and 17.29 - Phase H3): the
 * activities the user sees that were due in the range, by the month each
 * was due - every month of the range, a quiet one too - whom it is given to
 * or its type: how many were completed, of them on time - by their due
 * time - cancelled, still planned and overdue, and the shares completed and
 * on time.
 */
class ActivityCompletionReport extends ActivityReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $by   = array_key_exists($filters['group_by'], self::GROUPS) ? $filters['group_by'] : 'month';
        $zone = $this->calendar->timezone($workspace);
        $now  = now();
        $due  = $this->activities->dueBetween(
            $workspace,
            $this->visibility->scopeFor($workspace, $filters['user']),
            $this->narrowing($filters),
            CarbonImmutable::parse($filters['from'], $zone)->startOfDay()->utc(),
            CarbonImmutable::parse($filters['to'], $zone)->endOfDay()->utc(),
        );

        $month  = fn (string $key) => $this->period->label($key, 'month', $filters['date_format'] ?? null);
        $groups = $by === 'month'
            ? collect($this->period->buckets($filters['from'], $filters['to'], 'month'))->mapWithKeys(fn (string $key) => [$key => ['label' => $month($key), 'rows' => collect()]])->all()
            : [];

        foreach ($due as $activity) {
            $key = match ($by) {
                'month'    => $this->period->bucket($activity->due_at->copy()->setTimezone($zone)->toDateString(), 'month'),
                'assignee' => "assignee:{$activity->assigned_to}",
                default    => "type:{$activity->type_id}",
            };

            $groups[$key] ??= ['label' => match ($by) {
                'month'    => $month($key),
                'assignee' => $activity->assignee?->name ?? 'Nobody',
                default    => $activity->type?->name ?? 'No type',
            }, 'rows' => collect()];

            $groups[$key]['rows']->push($activity);
        }

        $groups = array_values($groups);

        if ($by !== 'month') {
            usort($groups, fn (array $a, array $b) => $b['rows']->count() <=> $a['rows']->count() ?: strcmp($a['label'], $b['label']));
        }

        $figures = function (Collection $rows) use ($now) {
            $completed = $rows->where('status', CrmActivity::COMPLETED);
            $onTime    = $completed->filter(fn (CrmActivity $activity) => $activity->completed_at !== null && $activity->completed_at->lte($activity->due_at))->count();
            $cancelled = $rows->where('status', CrmActivity::CANCELLED)->count();
            $planned   = $rows->where('status', CrmActivity::PLANNED);

            return [
                'due'          => $rows->count(),
                'completed'    => $completed->count(),
                'on_time'      => $onTime,
                'cancelled'    => $cancelled,
                'planned'      => $planned->count(),
                'overdue'      => $planned->filter(fn (CrmActivity $activity) => $activity->due_at->lt($now))->count(),
                'rate'         => $this->share($completed->count(), $rows->count() - $cancelled),
                'on_time_rate' => $this->share($onTime, $completed->count()),
            ];
        };

        return new ReportTable(
            title: $filters['report_name'] ?? 'Activity Completion',
            period: $this->between($filters),
            columns: [
                $this->column('group', self::GROUPS[$by]),
                $this->column('due', 'Due', 'number'),
                $this->column('completed', 'Completed', 'number'),
                $this->column('on_time', 'On Time', 'number'),
                $this->column('cancelled', 'Cancelled', 'number'),
                $this->column('planned', 'Still Planned', 'number'),
                $this->column('overdue', 'Overdue', 'number'),
                $this->column('rate', 'Completion Rate', 'percent'),
                $this->column('on_time_rate', 'On-Time Rate', 'percent'),
            ],
            rows: array_map(fn (array $group) => ['group' => $group['label'], ...$figures($group['rows'])], $groups),
            totals: ['group' => 'Total', ...$figures($due)],
            notes: [
                'The activities you see that were due from '.$this->day($filters['from'], $filters).' to '.$this->day($filters['to'], $filters).'. On time is completed by its due time; overdue, still planned with its due time passed.',
                'The completion rate is the share completed of those not cancelled; the on-time rate, the share of those completed that were on time.',
            ],
        );
    }
}
