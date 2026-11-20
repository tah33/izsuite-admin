<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS sale's payment as its pending list shows it (Doc 18 Phase D2): its
 * sale, its method as it was named then, the amount, its reference, when it
 * was taken, and whether it is pending, completed or declined.
 */
class PosOrderPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'order'     => $this->order?->number,
            'method'    => $this->method_name,
            'amount'    => (string) $this->amount,
            'currency'  => $this->order?->currency,
            'reference' => $this->reference,
            'status'    => $this->status,
            'taken_at'  => $this->order?->completed_at?->toIso8601String(),
        ];
    }
}
