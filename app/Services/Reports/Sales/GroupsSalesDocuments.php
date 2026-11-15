<?php

namespace App\Services\Reports\Sales;

use App\Models\User\SalesDocument;

/**
 * How the full Sales app's summaries group what they count (Doc 16 Phase
 * H3a and H3b): a document by its month, customer, salesperson or sales
 * team - each without one in a row of its own - and every month of a range.
 * For Report's subclasses.
 */
trait GroupsSalesDocuments
{
    /** @return array{0: string, 1: string} where $document falls by $group, and what the report calls it */
    protected function groupOf(SalesDocument $document, string $group, array $filters): array
    {
        if ($group === 'month') {
            return $this->month($document->document_date, $filters);
        }

        return match ($group) {
            'customer'    => ["contact:{$document->contact_id}", $this->figures->customerName($document)],
            'salesperson' => ["salesperson:{$document->salesperson_id}", $document->salesperson?->name ?? 'No salesperson'],
            default       => ["team:{$document->sales_team_id}", $document->salesTeam?->name ?? 'No team'],
        };
    }

    /** @return array{0: string, 1: string} $date's month - its first day, which sorts - and its name */
    protected function month(string $date, array $filters): array
    {
        $key = $this->period->bucket($date, 'month');

        return [$key, $this->period->label($key, 'month', $filters['date_format'] ?? null)];
    }

    /** A row from $blank for every month of the range, a quiet one too. */
    protected function months(array $filters, callable $blank): array
    {
        $rows = [];

        foreach ($this->period->buckets($filters['from'], $filters['to'], 'month') as $key) {
            $rows[$key] = $blank($this->period->label($key, 'month', $filters['date_format'] ?? null));
        }

        return $rows;
    }
}
