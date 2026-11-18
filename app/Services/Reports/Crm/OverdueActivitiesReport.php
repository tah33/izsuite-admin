<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmActivity;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Overdue Activities (Doc 17 sections 17.23 and 17.29 - Phase H3): the
 * planned activities the user sees past their due time now - as the CRM
 * Overview counts them - the longest overdue first, each with its type,
 * what it is for, whom it is given to and its full days overdue.
 */
class OverdueActivitiesReport extends ActivityReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $zone = $this->calendar->timezone($workspace);
        $now  = now();
        $rows = $this->activities->overdue($workspace, $this->visibility->scopeFor($workspace, $filters['user']), $this->narrowing($filters), $now);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Overdue Activities',
            period: 'As of '.$this->day($this->period->today($workspace), $filters),
            columns: [
                $this->column('due', 'Due', 'date'),
                $this->column('activity', 'Activity'),
                $this->column('type', 'Type'),
                $this->column('regarding', 'For'),
                $this->column('assignee', 'Assigned To'),
                $this->column('days', 'Days Overdue', 'number'),
            ],
            rows: $rows->map(fn (CrmActivity $activity) => [
                'due'       => $activity->due_at->copy()->setTimezone($zone)->toDateString(),
                'activity'  => $activity->subject ?: $activity->type?->name,
                'type'      => $activity->type?->name,
                'regarding' => $this->regarding($activity),
                'assignee'  => $activity->assignee?->name,
                'days'      => intdiv(max(0, $now->getTimestamp() - $activity->due_at->getTimestamp()), 86400),
            ])->all(),
            notes: ['The planned activities you see past their due time now, the longest overdue first. Days overdue are full days since the due time.'],
        );
    }

    /** What $activity is for: its opportunity - once its lead was converted into one - its lead, or its contact. */
    private function regarding(CrmActivity $activity): ?string
    {
        return match (true) {
            $activity->opportunity !== null => "Opportunity: {$activity->opportunity->name}",
            $activity->lead !== null        => "Lead: {$activity->lead->name}",
            $activity->contact !== null     => "Contact: {$activity->contact->name}",
            default                         => null,
        };
    }
}
