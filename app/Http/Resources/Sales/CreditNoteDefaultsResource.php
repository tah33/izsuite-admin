<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where a credit note's form starts (see CreditNoteService::defaults()): its
 * invoice - with what is left to credit of it, and the cash it took that has
 * not been refunded - the date, and each invoice line as it was invoiced, with
 * what issued credit notes left of it. Amounts go out as strings.
 */
class CreditNoteDefaultsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invoice = $this->resource['invoice'];

        return [
            'invoice'       => [
                'id'            => $invoice->id,
                'number'        => $invoice->number,
                'document_date' => $invoice->document_date,
                'currency'      => $invoice->currency,
                'customer'      => $invoice->customer_snapshot['name'] ?? $invoice->contact?->name,
                'grand_total'   => $invoice->grand_total,
                'creditable'    => $this->resource['creditable'],
                'cash'          => $this->resource['cash'],
            ],
            'document_date' => $this->resource['document_date'],
            'lines'         => array_values(array_map(fn (array $row) => [
                'source_line_id'     => $row['line']->id,
                'name'               => $row['line']->name,
                'unit'               => $row['line']->unit,
                'quantity'           => $row['line']->quantity,
                'unit_price'         => $row['line']->unit_price,
                'discount_type'      => $row['line']->discount_type,
                'discount_value'     => $row['line']->discount_value,
                'tax'                => $row['line']->tax_name !== null ? ['name' => $row['line']->tax_name, 'rate' => $row['line']->tax_rate, 'treatment' => $row['line']->tax_treatment] : null,
                'remaining_quantity' => $row['remaining_quantity'],
                'remaining_amount'   => $row['remaining_amount'],
            ], $this->resource['lines'])),
        ];
    }
}
