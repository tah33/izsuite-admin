<?php

namespace App\Http\Resources\Pos;

use App\Models\User\PosOrderPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS sale as the Orders page lists it (Doc 18 Phase G1): its receipt
 * number and when, its register, session and cashier, its customer, its
 * total and how it was paid, what returns have credited of it, and its
 * invoice - the formal one's number once it has one.
 */
class PosOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'number'       => $this->number,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'register'     => $this->register ? "{$this->register->name} ({$this->register->code})" : null,
            'session'      => $this->session ? ['id' => $this->session->id, 'number' => $this->session->number] : null,
            'cashier'      => $this->cashier?->name,
            'customer'     => $this->contact?->name,
            'total'        => (string) $this->grand_total,
            'currency'     => $this->currency,
            'paid_by'      => $this->payments->map(fn (PosOrderPayment $payment) => $payment->method_name.($payment->isPending() ? ' (pending)' : ''))->unique()->values()->all(),
            'returned'     => \App\Support\Decimal::round((string) ($this->returns_sum_total ?? '0')),
            'invoice'      => $this->invoice ? ['id' => $this->invoice->id, 'number' => $this->invoice->number] : null,
        ];
    }
}
