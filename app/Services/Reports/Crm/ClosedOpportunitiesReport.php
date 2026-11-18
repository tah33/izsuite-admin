<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Won and Lost Opportunities (Doc 17 sections 17.16 and 17.29 - Phase H2):
 * those the user sees won or lost in the range, the latest first - each
 * with its value in its own currency, a lost one's reason and competitor -
 * each row opening its opportunity, and what was won and lost in the base
 * currency at today's rates above.
 */
class ClosedOpportunitiesReport extends OpportunityReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base   = $workspace->base_currency;
        $rows   = $this->closed($workspace, $filters)->sortByDesc(fn (CrmOpportunity $row) => [$row->closed_on->toDateString(), $row->id])->values();
        $values = $this->forecast->baseValues($workspace, $rows);
        $won    = $rows->where('status', CrmOpportunity::WON);
        $lost   = $rows->where('status', CrmOpportunity::LOST);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Won and Lost Opportunities',
            period: $this->between($filters),
            columns: [
                $this->column('closed_on', 'Closed On', 'date'),
                $this->column('opportunity', 'Opportunity'),
                $this->column('contact', 'Contact'),
                $this->column('outcome', 'Outcome'),
                $this->column('owner', 'Owner'),
                $this->column('currency', 'Currency'),
                $this->column('value', 'Value', 'money'),
                $this->column('reason', 'Lost Reason'),
                $this->column('competitor', 'Competitor'),
            ],
            rows: $rows->map(fn (CrmOpportunity $row) => [
                'closed_on'   => $row->closed_on->toDateString(),
                'opportunity' => $row->name,
                'contact'     => $row->contact?->name,
                'outcome'     => $row->status === CrmOpportunity::WON ? 'Won' : 'Lost',
                'owner'       => $row->owner?->name,
                'value'       => Decimal::round((string) $row->expected_value),
                'reason'      => $row->lostReason?->name,
                'competitor'  => $row->lost_competitor,
                'currency'    => $row->currency,
                'link'        => "/crm/opportunities/{$row->id}",
            ])->all(),
            summary: [
                ['label' => "Won ({$won->count()})", 'value' => $this->sum($won, $values)['total'], 'currency' => $base],
                ['label' => "Lost ({$lost->count()})", 'value' => $this->sum($lost, $values)['total'], 'currency' => $base],
            ],
            notes: array_values(array_filter([
                $this->counting($filters, true).", the latest first. Values are in each opportunity's currency; what was won and lost above in {$base} at today's rates.",
                $this->unratedNote($this->sum($rows, $values)['unrated']),
                self::NOT_SALES,
            ])),
        );
    }
}
