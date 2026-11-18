<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmLead;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use Illuminate\Support\Collection;

/**
 * Lead Qualification Rate and Lead Conversion Rate (Doc 17 section 17.29 -
 * Phase H1): of the leads added in the range that the user sees, by month,
 * source, owner or sales team, the share qualified - qualified now or
 * converted since - or the share converted into an opportunity, each with
 * the counts behind it. The report's key says which (ReportService).
 */
class LeadRateReport extends LeadReport
{
    /** Each report's columns after the group's, and what its note says it counts. */
    private const REPORTS = [
        'lead-qualification-rate' => [
            ['leads' => 'Leads', 'qualified' => 'Qualified', 'unqualified' => 'Unqualified', 'working' => 'Being Worked', 'archived' => 'Archived', 'qualification_rate' => 'Qualification Rate'],
            'The qualification rate is the share qualified: qualified now, or converted since. Being worked is new, assigned or contacted.',
        ],
        'lead-conversion-rate' => [
            ['leads' => 'Leads', 'qualified' => 'Qualified', 'converted' => 'Converted', 'conversion_rate' => 'Conversion Rate', 'of_qualified' => 'Of the Qualified'],
            'The conversion rate is the share converted into an opportunity. Of the qualified is the share converted of those qualified: qualified now, or converted since.',
        ],
    ];

    private const RATES = ['qualification_rate', 'conversion_rate', 'of_qualified'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $key   = array_key_exists($filters['report_key'] ?? '', self::REPORTS) ? $filters['report_key'] : 'lead-conversion-rate';
        $by    = array_key_exists($filters['group_by'], self::GROUPS) ? $filters['group_by'] : 'month';
        $leads = $this->added($workspace, $filters);

        [$labels, $note] = self::REPORTS[$key];

        $columns = [$this->column('group', self::GROUPS[$by])];

        foreach ($labels as $column => $label) {
            $columns[] = $this->column($column, $label, in_array($column, self::RATES, true) ? 'percent' : 'number');
        }

        $figures = fn (Collection $leads) => array_intersect_key($this->rates($leads), $labels);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Lead Conversion Rate',
            period: $this->between($filters),
            columns: $columns,
            rows: array_map(fn (array $group) => ['group' => $group['label'], ...$figures($group['leads'])], $this->grouped($workspace, $leads, $by, $filters)),
            totals: ['group' => 'Total', ...$figures($leads)],
            notes: [$this->counting($filters).'.', $note, self::NOT_SALES],
        );
    }

    /** Every count and rate either report shows, of $leads. */
    private function rates(Collection $leads): array
    {
        $qualified = $this->counted($leads, self::QUALIFIED);
        $converted = $this->counted($leads, [CrmLead::CONVERTED]);

        return [
            'leads'              => $leads->count(),
            'qualified'          => $qualified,
            'unqualified'        => $this->counted($leads, [CrmLead::UNQUALIFIED]),
            'working'            => $this->counted($leads, self::WORKING),
            'archived'           => $this->counted($leads, [CrmLead::ARCHIVED]),
            'converted'          => $converted,
            'qualification_rate' => $this->share($qualified, $leads->count()),
            'conversion_rate'    => $this->share($converted, $leads->count()),
            'of_qualified'       => $this->share($converted, $qualified),
        ];
    }
}
