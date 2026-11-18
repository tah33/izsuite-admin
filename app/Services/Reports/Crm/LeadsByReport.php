<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmLead;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use Illuminate\Support\Collection;

/**
 * Leads by Source, Leads by Owner and Leads by Status (Doc 17 section 17.29
 * - Phase H1), the grouping each is built with fixed (ReportCatalog::
 * GROUPED): the leads added in the range that the user sees, by the source
 * each came with - kept when it is reassigned or converted (17.8) - by whom
 * each is given to now, or by where each stands now. By source or owner,
 * each row splits its leads into those still being worked, ready to
 * convert, converted and closed - unqualified or archived - with the share
 * converted; by status, each status's share.
 */
class LeadsByReport extends LeadReport
{
    private const CLOSED = [CrmLead::UNQUALIFIED, CrmLead::ARCHIVED];

    private const NAMES = ['source' => 'Lead Source', 'owner' => 'Owner', 'status' => 'Status'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $by     = array_key_exists($filters['group_by'], self::NAMES) ? $filters['group_by'] : 'source';
        $leads  = $this->added($workspace, $filters);
        $groups = $this->grouped($workspace, $leads, $by, $filters);

        if ($by === 'status') {
            return new ReportTable(
                title: $filters['report_name'] ?? 'Leads by Status',
                period: $this->between($filters),
                columns: [$this->column('group', 'Status'), $this->column('leads', 'Leads', 'number'), $this->column('share', 'Share', 'percent')],
                rows: array_map(fn (array $group) => ['group' => $group['label'], 'leads' => $group['leads']->count(), 'share' => $this->share($group['leads']->count(), $leads->count())], $groups),
                totals: ['group' => 'Total', 'leads' => $leads->count(), 'share' => $this->share($leads->count(), $leads->count())],
                notes: [$this->counting($filters).', by where each stands now.', self::NOT_SALES],
            );
        }

        return new ReportTable(
            title: $filters['report_name'] ?? 'Leads by '.self::NAMES[$by],
            period: $this->between($filters),
            columns: [
                $this->column('group', self::NAMES[$by]),
                $this->column('leads', 'Leads', 'number'),
                $this->column('working', 'Being Worked', 'number'),
                $this->column('ready', 'Ready to Convert', 'number'),
                $this->column('converted', 'Converted', 'number'),
                $this->column('closed', 'Closed', 'number'),
                $this->column('rate', 'Conversion Rate', 'percent'),
            ],
            rows: array_map(fn (array $group) => ['group' => $group['label'], ...$this->outcomes($group['leads'])], $groups),
            totals: ['group' => 'Total', ...$this->outcomes($leads)],
            notes: [
                $this->counting($filters).($by === 'source'
                    ? ', by the source each came with: it stays when a lead is given to someone else or converted.'
                    : ', by whom each is given to now; No owner is the queue.'),
                'Being worked is new, assigned or contacted; ready to convert, qualified and not converted yet; closed, unqualified or archived. The conversion rate is the share converted.',
                self::NOT_SALES,
            ],
        );
    }

    /** @return array{leads: int, working: int, ready: int, converted: int, closed: int, rate: ?string} */
    private function outcomes(Collection $leads): array
    {
        $converted = $this->counted($leads, [CrmLead::CONVERTED]);

        return [
            'leads'     => $leads->count(),
            'working'   => $this->counted($leads, self::WORKING),
            'ready'     => $this->counted($leads, [CrmLead::QUALIFIED]),
            'converted' => $converted,
            'closed'    => $this->counted($leads, self::CLOSED),
            'rate'      => $this->share($converted, $leads->count()),
        ];
    }
}
