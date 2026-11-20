<?php

namespace App\Http\Resources\Pos;

use App\Models\User\PosOrderPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A completed POS sale as the selling screen shows it (Doc 18 Phase C2):
 * its receipt number, total and the change given, and how it was paid -
 * and (Phase C4) its customer's e-mail address, to send the receipt to.
 */
class PosOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'number'   => $this->number,
            'total'    => (string) $this->grand_total,
            'change'   => (string) $this->change_given,
            // Phase D2: a payment waiting to be confirmed says so.
            'payments' => $this->payments->map(fn (PosOrderPayment $payment) => ['method' => $payment->method_name, 'amount' => (string) $payment->amount, 'pending' => $payment->isPending()])->all(),
            'email'    => $this->contact?->email,
            // Phase D3a: what was left on the customer's account, and a warning that they are over their credit limit.
            'on_account'     => (string) $this->on_account,
            'credit_warning' => $this->resource->creditWarning,
            // Phase D3b: how much of the customer's own credit paid it.
            'credit'         => (string) $this->credit_applied,
            // Phase D4: its customer - a formal invoice is for a named one.
            'contact_id'     => $this->contact_id,
            // Phase E3: the exchange's return that paid it, how much, and the difference paid back in cash.
            'exchange'       => $this->exchange_return_id === null ? null : [
                'number' => $this->exchangeReturn?->number, 'amount' => (string) $this->exchange_credit, 'paid_back' => (string) $this->exchange_refunded,
            ],
        ];
    }
}
