<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Concerns\HidesValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Credit applied to an invoice (Doc 16 Phase D1), on the invoice's page, its
 * credit note's or its advance's: the invoice, where the credit came from,
 * how much, and - once taken off - when, by whom and why.
 */
class SalesAllocationResource extends JsonResource
{
    use HidesValues;

    public function toArray(Request $request): array
    {
        return $this->hideValues('sales', [
            'id'              => $this->id,
            'status'          => $this->status,
            'allocation_date' => $this->allocation_date,
            'invoice'         => ['id' => $this->invoice_id, 'number' => $this->invoice?->number],
            'source'          => $this->customer_advance_id
                ? ['kind' => 'advance', 'id' => $this->customer_advance_id, 'number' => $this->advance?->number]
                : ['kind' => 'credit_note', 'id' => $this->credit_note_id, 'number' => $this->creditNote?->number],
            'amount'          => $this->amount,
            'made_by'         => $this->creator?->name,
            'removed_at'      => $this->removed_at?->toIso8601String(),
            'removed_by'      => $this->remover?->name,
            'removal_reason'  => $this->removal_reason,
        ], ['amount']);
    }
}
