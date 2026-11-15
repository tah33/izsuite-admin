<?php

namespace App\Services\Reports\Sales;

use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Support\Decimal;

/**
 * What Quotation Performance and Quotation Conversion Rate share (Doc 16
 * section 16.26 - Phase H3a): the quotations of the period that went out -
 * not drafts or cancelled ones - grouped by month, customer, salesperson or
 * sales team, each counted where it stands now: accepted, converted,
 * rejected, expired (sent, and its expiry passed by today) or still open.
 * Won is accepted or converted. Values are grand totals in the base
 * currency, as the Sales Overview counts them.
 */
abstract class QuotationReport extends Report
{
    use GroupsSalesDocuments;

    public const GROUPS = ['month' => 'Month', 'customer' => 'Customer', 'salesperson' => 'Salesperson', 'team' => 'Sales Team'];

    /**
     * A row per group - every month of the range when by month, a quiet one
     * too; otherwise the most valuable first - with its quotations' count and
     * value, how many stand each way, and those won.
     */
    protected function outcomes(Workspace $workspace, array $filters): array
    {
        $group = $this->grouping($filters);
        $rows  = $group === 'month' ? $this->months($filters, fn (string $label) => $this->blank($label)) : [];

        foreach ($this->sales->quotationsOut($workspace, $filters['from'], $filters['to'], $filters) as $quotation) {
            [$key, $label] = $this->groupOf($quotation, $group, $filters);
            $outcome       = $this->outcome($quotation, $filters['as_of']);
            $value         = Decimal::round(Decimal::of($quotation->base_grand_total));

            $rows[$key] ??= $this->blank($label);
            $rows[$key]['quotations']++;
            $rows[$key][$outcome]++;
            $rows[$key]['value'] = $this->add($rows[$key]['value'], $value);

            if (in_array($outcome, ['accepted', 'converted'], true)) {
                $rows[$key]['won']++;
                $rows[$key]['won_value'] = $this->add($rows[$key]['won_value'], $value);
            }
        }

        if ($group !== 'month') {
            uasort($rows, fn (array $a, array $b) => bccomp($b['value'], $a['value'], 2) ?: strcmp($a['group'], $b['group']));
        }

        return array_values($rows);
    }

    /** The grouping asked for - by month, unless it is one of these. */
    protected function grouping(array $filters): string
    {
        return array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'month';
    }

    /** Where it stands now: one sent whose expiry passed before $today has expired; one sent and still good is open. */
    private function outcome(SalesDocument $quotation, string $today): string
    {
        return match ($quotation->status) {
            SalesDocumentStatus::Accepted  => 'accepted',
            SalesDocumentStatus::Converted => 'converted',
            SalesDocumentStatus::Rejected  => 'rejected',
            default                        => $quotation->expiry_date && $quotation->expiry_date < $today ? 'expired' : 'open',
        };
    }

    private function blank(string $label): array
    {
        return ['group' => $label, 'quotations' => 0, 'value' => '0.00', 'accepted' => 0, 'converted' => 0, 'rejected' => 0, 'expired' => 0, 'open' => 0, 'won' => 0, 'won_value' => '0.00'];
    }
}
