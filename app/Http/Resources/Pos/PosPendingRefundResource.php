<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS return's refund as its pending list shows it (Doc 18 Phase E2): the
 * return and its sale, the method, what waits to be paid out - or, once
 * settled, what was - when it was made, and whether it waits, was paid or
 * was declined.
 */
class PosPendingRefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'number'   => $this->number,
            'order'    => $this->order?->number,
            'method'   => $this->refund_method_name,
            'amount'   => (string) ($this->refund_status === 'pending' ? $this->refund_due : $this->refunded),
            'currency' => $this->currency,
            'status'   => $this->refund_status,
            'made_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
