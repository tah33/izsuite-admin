<?php

namespace App\Http\Resources\Pos;

use App\Models\User\PosReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A POS return as the selling screen shows it done (Doc 18 Phase E1): its number, what it credits, what was paid back and how, and its credit note. */
class PosReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'number'      => $this->number,
            'total'       => (string) $this->total,
            'refunded'    => (string) $this->refunded,
            'refunded_by' => $this->refund_method_name,
            // Phase E2: what waits to be paid out.
            'pending'     => (string) $this->refund_due,
            'credit_note' => $this->creditNote?->number,
            // Phase E3: made as an exchange, the credit that pays the replacement's sale, and its customer.
            'exchange'    => $this->refund_method !== PosReturn::EXCHANGE ? null : [
                'credit' => (string) $this->creditNote?->balance_due, 'customer' => ['id' => $this->contact_id, 'name' => $this->contact?->name],
            ],
        ];
    }
}
