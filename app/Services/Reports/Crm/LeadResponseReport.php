<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmLead;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/**
 * Lead Response Time (Doc 17 sections 17.23 and 17.29 - Phase H1): of the
 * leads added in the range that the user sees, by month, source, owner or
 * sales team - how many were contacted, how many within the lead response
 * period (Settings → CRM, Phase G3b) and how many not yet, the average time
 * from added to first contacted (Phase G3a) in hours, and the share
 * answered in time.
 */
class LeadResponseReport extends LeadReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $by    = array_key_exists($filters['group_by'], self::GROUPS) ? $filters['group_by'] : 'month';
        $days  = $this->settings->forWorkspace($workspace)->lead_response_days;
        $leads = $this->added($workspace, $filters);
        $span  = $days === 1 ? '1 Day' : "{$days} Days";

        return new ReportTable(
            title: $filters['report_name'] ?? 'Lead Response Time',
            period: $this->between($filters),
            columns: [
                $this->column('group', self::GROUPS[$by]),
                $this->column('leads', 'Leads', 'number'),
                $this->column('contacted', 'Contacted', 'number'),
                $this->column('in_time', "Within {$span}", 'number'),
                $this->column('waiting', 'Not Contacted', 'number'),
                $this->column('average', 'Average Response (Hours)', 'number'),
                $this->column('in_time_rate', 'Answered in Time', 'percent'),
            ],
            rows: array_map(fn (array $group) => ['group' => $group['label'], ...$this->responses($group['leads'], $days)], $this->grouped($workspace, $leads, $by, $filters)),
            totals: ['group' => 'Total', ...$this->responses($leads, $days)],
            notes: [
                $this->counting($filters).'. A response is the first contact after a lead was added: marked contacted, an activity done for it, or an e-mail sent to it from izSuite.',
                'Answered in time is the share of the leads contacted within '.strtolower($span).' of being added, the lead response period in Settings → CRM.',
                self::NOT_SALES,
            ],
        );
    }

    /** @return array{leads: int, contacted: int, in_time: int, waiting: int, average: ?string, in_time_rate: ?string} */
    private function responses(Collection $leads, int $days): array
    {
        $seconds = $leads->filter(fn (CrmLead $lead) => $lead->first_contacted_at !== null)
            ->map(fn (CrmLead $lead) => max(0, $lead->first_contacted_at->getTimestamp() - $lead->created_at->getTimestamp()))
            ->values();
        $inTime  = $seconds->filter(fn (int $taken) => $taken <= $days * 86400)->count();

        return [
            'leads'        => $leads->count(),
            'contacted'    => $seconds->count(),
            'in_time'      => $inTime,
            'waiting'      => $leads->count() - $seconds->count(),
            'average'      => $seconds->isEmpty() ? null : Decimal::round(bcdiv((string) $seconds->sum(), (string) ($seconds->count() * 3600), 4), 1),
            'in_time_rate' => $this->share($inTime, $leads->count()),
        ];
    }
}
