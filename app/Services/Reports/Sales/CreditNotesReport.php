<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * Credit Note Details (Doc 08 section 22): issued credit notes by their dates
 * - why, against which invoice, and what became of their credit: applied to
 * the invoice, refunded, or still available. Each in its own currency; the
 * total in the base one.
 */
class CreditNotesReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];

        foreach ($this->sales->issued($workspace, DocumentType::CreditNote, $filters['from'], $filters['to'], $filters) as $note) {
            $rows[] = [
                'number'        => $note->number,
                'document_date' => $note->document_date,
                'customer'      => $this->figures->customerName($note),
                'invoice'       => $note->source?->number,
                'reason'        => $note->reason,
                'currency'      => $note->currency,
                'total'         => (string) $note->grand_total,
                'applied'       => (string) $note->applied_amount,
                'refunded'      => (string) $note->refunded_amount,
                'available'     => (string) $note->balance_due,
                'status'        => Str::headline((string) $note->payment_status),
                'base_total'    => Decimal::round(Decimal::of($note->base_grand_total)),
                'link'          => "/sales/credit-notes/{$note->id}",
            ];
        }

        return new ReportTable(
            title: 'Credit Note Details',
            period: $this->between($filters),
            columns: [
                $this->column('number', 'Credit Note'),
                $this->column('document_date', 'Date', 'date'),
                $this->column('customer', 'Customer'),
                $this->column('invoice', 'Invoice'),
                $this->column('reason', 'Reason'),
                $this->column('currency', 'Currency'),
                $this->column('total', 'Total', 'money'),
                $this->column('applied', 'Applied', 'money'),
                $this->column('refunded', 'Refunded', 'money'),
                $this->column('available', 'Available', 'money'),
                $this->column('status', 'Credit'),
                $this->column('base_total', "Total ({$base})", 'money', $base),
            ],
            rows: $rows,
            totals: ['number' => 'Total', 'base_total' => Decimal::sum(array_column($rows, 'base_total'))],
            notes: ['A credit note pays off what its invoice still owes first; what is left is refunded or stays available.'],
        );
    }
}
