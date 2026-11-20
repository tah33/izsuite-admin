<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS return as the Returns and Refunds page lists it (Doc 18 Phase G1):
 * its number and when, the sale it came from, its register, session and
 * cashier, its customer, what it credits, how it was paid back - what was
 * paid out, what waits, whether it was declined - and, made as an exchange,
 * the sale of its replacement.
 */
class PosReturnListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'number'      => $this->number,
            'made_at'     => $this->created_at?->toIso8601String(),
            'sale'        => $this->order?->number,
            'register'    => $this->register ? "{$this->register->name} ({$this->register->code})" : null,
            'session'     => $this->session ? ['id' => $this->session->id, 'number' => $this->session->number] : null,
            'cashier'     => $this->cashier?->name,
            'customer'    => $this->contact?->name,
            'total'       => (string) $this->total,
            'currency'    => $this->currency,
            'refund'      => $this->refund_method,
            'refunded_by' => $this->refund_method_name,
            'refunded'    => (string) $this->refunded,
            'pending'     => (string) $this->refund_due,
            'status'      => $this->refund_status,
            'exchange'    => $this->exchangeOrder?->number,
        ];
    }
}
