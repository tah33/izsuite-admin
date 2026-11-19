<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS register (Doc 18 Phase A2) as the Registers page shows and edits it:
 * its code, location, currency, price list, cash account, receipt number
 * prefix, the payment methods it takes, its cashiers and its status - and
 * whether sessions were held at it (Phase B1), which keep it from deletion.
 */
class PosRegisterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $named = fn ($record) => $record ? ['id' => $record->id, 'name' => $record->name] : null;

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'code'            => $this->code,
            'location'        => $named($this->location),
            'currency'        => $this->currency,
            'price_list'      => $named($this->priceList),
            'cash_account'    => $named($this->cashAccount),
            'receipt_prefix'  => $this->receipt_prefix,
            'status'          => $this->status,
            'payment_methods' => $this->paymentMethods->map($named)->values(),
            'cashiers'        => $this->cashiers->map($named)->values(),
            'has_sessions'    => (bool) $this->sessions_exists,
        ];
    }
}
